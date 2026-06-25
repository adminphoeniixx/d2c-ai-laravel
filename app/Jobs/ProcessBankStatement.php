<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\Tenant\BankingController;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\BankTransaction;
use App\Services\Banking\BankStatementParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessBankStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300; // 5 minutes — enough for heavy model

    public function __construct(
        public string $companyId,
        public string $companySlug,
        public string $storagePath,  // path under storage/app/
        public string $extension,
        public ?string $password,
        public string $originalName,
        public int $fileSize,
        public int $logId,           // banking_upload_logs.id to update
    ) {}

    public function handle(): void
    {
        $filePath = Storage::disk('local')->path($this->storagePath);

        if (!file_exists($filePath)) {
            $this->updateLog($this->logId, 'failed', 'Uploaded file not found on disk.');
            return;
        }

        // Initialize tenant context
        $company = \App\Models\Company::find($this->companyId);
        if (!$company) {
            $this->updateLog($this->logId, 'failed', 'Company not found.');
            return;
        }

        tenancy()->initialize($company);

        try {
            $parser = new BankStatementParser();
            $result = $parser->parseFile($filePath, $this->extension, $this->password);

            $txCount  = count($result['transactions'] ?? []);
            $bankInfo = $result['bank'] ?? [];
            $hasError = !empty($result['errors']) || !empty($result['needs_password']);

            // Update log with parse results
            DB::connection('pgsql')->table('banking_upload_logs')
                ->where('id', $this->logId)
                ->update([
                    'bank_detected'       => $bankInfo['name'] ?? null,
                    'bank_format'         => $bankInfo['format'] ?? null,
                    'transactions_parsed' => $txCount,
                    'error_message'       => $hasError ? implode(' | ', $result['errors'] ?? []) : null,
                    'parse_steps'         => json_encode($parser->trace),
                    'sample_transactions' => json_encode(array_slice($result['transactions'] ?? [], 0, 5)),
                    'status'              => $hasError ? 'failed' : ($txCount === 0 ? 'empty' : 'processing'),
                    'updated_at'          => now(),
                ]);

            if ($hasError || empty($result['transactions'])) {
                tenancy()->end();
                Storage::disk('local')->delete($this->storagePath);
                return;
            }

            // Find or create bank account
            $bankName = $bankInfo['name'] ?? 'Unknown Bank';
            $account  = BankAccount::where('bank_name', 'ilike', "%{$bankName}%")->first();

            if (!$account) {
                $last4   = !empty($bankInfo['account_number']) ? substr($bankInfo['account_number'], -4) : '';
                $account = BankAccount::create([
                    'name'           => $bankName . ($last4 ? " •{$last4}" : ''),
                    'bank_name'      => strtolower(explode(' ', $bankName)[0]),
                    'account_number' => $bankInfo['account_number'] ?? null,
                    'ifsc_code'      => $bankInfo['ifsc'] ?? null,
                    'account_type'   => 'current',
                ]);
            }

            $batch    = Str::random(16);
            $imported = 0;
            $skipped  = 0;

            foreach ($result['transactions'] as $t) {
                $exists = BankTransaction::where('bank_account_id', $account->id)
                    ->where('date',        $t['date'])
                    ->where('type',        $t['type'])
                    ->where('amount',      $t['amount'])
                    ->where('description', $t['description'] ?? '')
                    ->exists();

                if ($exists) { $skipped++; continue; }

                BankTransaction::create([
                    'bank_account_id' => $account->id,
                    'date'            => $t['date'],
                    'type'            => $t['type'],
                    'amount'          => $t['amount'],
                    'balance'         => $t['balance'] ?? null,
                    'description'     => $t['description'] ?? null,
                    'reference'       => $t['reference'] ?? null,
                    'category'        => $t['category'] ?? 'other',
                    'vendor'          => $t['vendor'] ?? 'Miscellaneous',
                    'source'          => 'import',
                    'upload_batch'    => $batch,
                    'raw_data'        => $t,
                ]);
                $imported++;
            }

            DB::connection('pgsql')->table('banking_upload_logs')
                ->where('id', $this->logId)
                ->update([
                    'status'                 => $imported > 0 ? 'success' : 'skipped',
                    'transactions_imported'  => $imported,
                    'transactions_skipped'   => $skipped,
                    'updated_at'             => now(),
                ]);

            Log::info('ProcessBankStatement done', [
                'company'  => $this->companySlug,
                'file'     => $this->originalName,
                'imported' => $imported,
                'skipped'  => $skipped,
            ]);

        } catch (\Throwable $e) {
            Log::error('ProcessBankStatement failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            $this->updateLog($this->logId, 'failed', $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            tenancy()->end();
            // Clean up the temp file
            Storage::disk('local')->delete($this->storagePath);
        }
    }

    private function updateLog(int $id, string $status, string $error): void
    {
        try {
            DB::connection('pgsql')->table('banking_upload_logs')
                ->where('id', $id)
                ->update(['status' => $status, 'error_message' => $error, 'updated_at' => now()]);
        } catch (\Throwable $e) {}
    }
}
