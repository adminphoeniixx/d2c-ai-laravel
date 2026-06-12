<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CompanyKyc;
use App\Services\BunnyCDN;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class KycController extends Controller
{
    public function index(string $tenant)
    {
        $company = Auth::user()->company;
        $kyc     = CompanyKyc::where('company_id', $company->id)->first();

        return Inertia::render('Tenant/KYC/Index', [
            'kyc'    => $kyc,
            'status' => $kyc?->status ?? 'pending',
        ]);
    }

    public function submit(Request $request, string $tenant)
    {
        $data = $request->validate([
            'legal_name'          => 'required|string|max:200',
            'business_type'       => 'required|string',
            'gstin'               => 'nullable|string|max:15',
            'pan'                 => 'nullable|string|max:10',
            'address_line1'       => 'required|string|max:200',
            'address_line2'       => 'nullable|string|max:200',
            'city'                => 'required|string|max:100',
            'state'               => 'required|string|max:100',
            'pincode'             => 'required|string|max:6',
            'bank_account_name'   => 'required|string|max:200',
            'bank_account_number' => 'required|string|max:30',
            'bank_ifsc'           => 'required|string|max:11',
            'bank_name'           => 'required|string|max:100',
            'documents.*'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $company = Auth::user()->company;
        $cdn     = app(BunnyCDN::class);

        // Get existing docs to merge
        $existingKyc = CompanyKyc::where('company_id', $company->id)->first();
        $existingDocs = $existingKyc?->documents ?? [];

        // Upload new documents
        $newDocs = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $url = $cdn->upload($file, "kyc/{$company->id}");
                $newDocs[] = [
                    'url'          => $url,
                    'name'         => $file->getClientOriginalName(),
                    'type'         => $file->getClientMimeType(),
                    'uploaded_at'  => now()->toDateTimeString(),
                ];
            }
        }

        $allDocs = array_merge($existingDocs, $newDocs);

        CompanyKyc::updateOrCreate(
            ['company_id' => $company->id],
            array_merge($data, [
                'status'       => 'submitted',
                'submitted_at' => now(),
                'documents'    => $allDocs,
            ])
        );

        return back()->with('success', 'KYC submitted successfully. We will review within 24-48 hours.');
    }

    public function deleteDocument(Request $request, string $tenant)
    {
        $company = Auth::user()->company;
        $kyc     = CompanyKyc::where('company_id', $company->id)->firstOrFail();

        $url  = $request->input('url');
        $docs = array_filter($kyc->documents ?? [], fn($d) => $d['url'] !== $url);
        $kyc->update(['documents' => array_values($docs)]);

        // Optionally delete from CDN
        app(BunnyCDN::class)->delete($url);

        return back()->with('success', 'Document removed.');
    }
}
