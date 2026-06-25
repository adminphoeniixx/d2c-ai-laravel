<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBankStatement;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\BankTransaction;
use App\Services\Banking\BankStatementParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BankingController extends Controller
{
    public function index(): Response
    {
        $accounts = BankAccount::withCount('transactions')
            ->withSum(['transactions as credit_total' => fn($q) => $q->where('type','credit')], 'amount')
            ->withSum(['transactions as debit_total'  => fn($q) => $q->where('type','debit')], 'amount')
            ->get();

        $stats = BankTransaction::selectRaw("
            SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) as total_credit,
            SUM(CASE WHEN type='debit' THEN amount ELSE 0 END) as total_debit,
            COUNT(*) as total_transactions
        ")->first();

        // Spend by category
        $categorySpend = BankTransaction::where('type','debit')
            ->selectRaw("category, SUM(amount) as total")
            ->groupBy('category')->orderByDesc('total')->get();

        // Spend by vendor (top 10)
        $vendorSpend = BankTransaction::where('type','debit')
            ->selectRaw("vendor, SUM(amount) as total, COUNT(*) as count")
            ->groupBy('vendor')->orderByDesc('total')->limit(15)->get();

        // Monthly totals
        $monthlyTotals = BankTransaction::selectRaw("
            TO_CHAR(date, 'YYYY-MM') as month,
            SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) as credit,
            SUM(CASE WHEN type='debit' THEN amount ELSE 0 END) as debit,
            COUNT(*) as count
        ")->groupBy(\DB::raw("TO_CHAR(date, 'YYYY-MM')"))
          ->orderByDesc(\DB::raw("TO_CHAR(date, 'YYYY-MM')"))
          ->limit(12)->get();

        return Inertia::render('Tenant/Banking/Index', [
            'accounts'      => $accounts,
            'stats'         => $stats,
            'categorySpend' => $categorySpend,
            'vendorSpend'   => $vendorSpend,
            'monthlyTotals' => $monthlyTotals,
        ]);
    }

    /**
     * Smart upload — auto-detect bank, auto-create account if needed.
     * No need to create bank account first.
     */
    public function smartUpload(Request $request): RedirectResponse
    {
        $request->validate([
            'statement'    => ['required', 'file', 'max:20480'],
            'pdf_password' => ['nullable', 'string', 'max:100'],
        ]);

        $file      = $request->file('statement');
        $extension = strtolower($file->getClientOriginalExtension());
        $password  = $request->input('pdf_password') ?: null;
        $fileName  = $file->getClientOriginalName();
        $fileSize  = $file->getSize();
        $company   = app('current_company');

        // Save file to persistent storage so the background job can access it
        $storagePath = 'banking_uploads/' . Str::uuid() . '.' . $extension;
        Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));

        // Also save debug copy
        copy($file->getRealPath(), '/tmp/last_banking_upload.' . $extension);

        // Create audit log entry immediately so we can track status
        $logId = DB::connection('pgsql')->table('banking_upload_logs')->insertGetId([
            'company_id'           => $company->id,
            'company_slug'         => $company->slug,
            'filename'             => $fileName,
            'file_type'            => $extension,
            'file_size'            => $fileSize,
            'status'               => 'queued',
            'transactions_parsed'  => 0,
            'transactions_imported'=> 0,
            'transactions_skipped' => 0,
            'file_preview'         => mb_substr(file_get_contents($file->getRealPath()), 0, 600),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        // Dispatch background job — no timeout issues
        ProcessBankStatement::dispatch(
            $company->id,
            $company->slug,
            $storagePath,
            $extension,
            $password,
            $fileName,
            $fileSize,
            $logId,
        )->onQueue('default');

        return back()->with('success', '⏳ Statement queued for processing. Transactions will appear within 1-2 minutes. Refresh the page to see results.');
    }

    /**
     * Ledger for a bank account — with vendor filter, date range, month filter.
     */
    public function ledger(Request $request, string $tenant, string $accountId): Response
    {
        $account = BankAccount::findOrFail($accountId);
        $month = $request->input('month');
        $from = $request->input('from');
        $to = $request->input('to');
        $vendor = $request->input('vendor');
        $type = $request->input('type');

        $query = BankTransaction::where('bank_account_id', $account->id);

        // Date filters
        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        } elseif ($month) {
            $query->whereYear('date', substr($month, 0, 4))
                  ->whereMonth('date', substr($month, 5, 2));
        }
        // No default month filter — show ALL transactions if no filter set

        if ($vendor && $vendor !== 'all') {
            $query->where('vendor', $vendor);
        }
        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        $transactions = (clone $query)->orderByDesc('date')->paginate(50)->withQueryString();

        // Stats for filtered view
        $filteredStats = (clone $query)->selectRaw("
            SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) as total_credit,
            SUM(CASE WHEN type='debit' THEN amount ELSE 0 END) as total_debit,
            COUNT(CASE WHEN type='credit' THEN 1 END) as credit_count,
            COUNT(CASE WHEN type='debit' THEN 1 END) as debit_count
        ")->first();

        // All vendors for this account (for filter dropdown)
        $vendors = BankTransaction::where('bank_account_id', $account->id)
            ->select('vendor')
            ->selectRaw("SUM(CASE WHEN type='debit' THEN amount ELSE 0 END) as debit_total")
            ->selectRaw("SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) as credit_total")
            ->selectRaw("COUNT(*) as count")
            ->groupBy('vendor')
            ->orderByDesc(\DB::raw("COUNT(*)"))
            ->get();

        // Available months
        $months = BankTransaction::where('bank_account_id', $account->id)
            ->selectRaw("DISTINCT TO_CHAR(date, 'YYYY-MM') as month")
            ->orderByDesc(\DB::raw("TO_CHAR(date, 'YYYY-MM')"))
            ->pluck('month');

        return Inertia::render('Tenant/Banking/Ledger', [
            'account'      => $account,
            'transactions' => $transactions,
            'stats'        => $filteredStats,
            'vendors'      => $vendors,
            'months'       => $months,
            'filters'      => $request->only(['month', 'from', 'to', 'vendor', 'type']),
        ]);
    }

    /**
     * Legacy upload (for existing account).
     */
    public function uploadStatement(Request $request, string $tenant, string $accountId): RedirectResponse
    {
        $account = BankAccount::findOrFail($accountId);
        $request->validate([
            'statement'    => ['required', 'file', 'max:20480'],
            'pdf_password' => ['nullable', 'string', 'max:100'],
        ]);

        $file      = $request->file('statement');
        $extension = strtolower($file->getClientOriginalExtension());
        $password  = $request->input('pdf_password');

        $parser = new BankStatementParser();
        $result = $parser->parseFile($file->getRealPath(), $extension, $password);

        // Surface password-required error clearly so the user knows to retry
        if (!empty($result['needs_password'])) {
            return back()->with('error', 'This PDF is password-protected. Please enter the password and try again.');
        }

        if (!empty($result['errors'])) {
            return back()->with('error', implode(' ', $result['errors']));
        }

        if (empty($result['transactions'])) {
            return back()->with('error', 'No transactions found in this file. Make sure it\'s a valid bank statement export.');
        }

        // Categorization already applied inside parseFile.

        $batch = Str::random(16);
        $imported = 0; $skipped = 0;

        foreach ($result['transactions'] as $t) {
            $exists = BankTransaction::where('bank_account_id', $account->id)
                ->where('date', $t['date'])->where('type', $t['type'])
                ->where('amount', $t['amount'])->where('description', $t['description'] ?? '')->exists();
            if ($exists) { $skipped++; continue; }

            BankTransaction::create([
                'bank_account_id' => $account->id,
                'date' => $t['date'], 'type' => $t['type'], 'amount' => $t['amount'],
                'balance' => $t['balance'] ?? null, 'description' => $t['description'] ?? null,
                'reference' => $t['reference'] ?? null, 'category' => $t['category'] ?? 'other',
                'vendor' => $t['vendor'] ?? 'Miscellaneous',
                'source' => 'import', 'upload_batch' => $batch, 'raw_data' => $t,
            ]);
            $imported++;
        }

        $msg = "Imported {$imported} transactions.";
        if ($skipped > 0) $msg .= " ({$skipped} duplicates skipped)";
        return back()->with('success', $msg);
    }

    public function createAccount(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required','string','max:100'],
            'bank_name' => ['required','string','max:50'],
            'account_number' => ['nullable','string','max:30'],
            'ifsc_code' => ['nullable','string','max:15'],
            'account_type' => ['required','in:current,savings,cc'],
        ]);
        BankAccount::create($v);
        return back()->with('success', "Account {$v['name']} added.");
    }

    public function deleteAccount(Request $request, string $tenant, string $accountId): RedirectResponse
    {
        $account = BankAccount::findOrFail($accountId);
        $account->transactions()->delete();
        $account->delete();
        return back()->with('success', 'Account deleted.');
    }
}
