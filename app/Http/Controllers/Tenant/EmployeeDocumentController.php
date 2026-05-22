<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeDocument;
use App\Services\BunnyCDN;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeDocumentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'name'        => ['required', 'string', 'max:200'],
            'type'        => ['required', 'in:aadhaar,pan,resume,offer_letter,id_proof,payslip,other'],
            'file'        => ['required', 'file', 'max:10240'],
            'notes'       => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $employee = Employee::findOrFail($validated['employee_id']);
        $company = app('current_company');

        $bunny = new BunnyCDN();
        $fileUrl = $bunny->upload($file, "documents/{$company->id}/{$employee->employee_id}");

        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'name'        => $validated['name'],
            'type'        => $validated['type'],
            'file_name'   => $file->getClientOriginalName(),
            'file_url'    => $fileUrl,
            'file_size'   => $this->formatSize($file->getSize()),
            'mime_type'   => $file->getMimeType(),
            'notes'       => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function destroy(Request $request, string $tenant, string $id): RedirectResponse
    {
        $doc = EmployeeDocument::findOrFail($id);
        (new BunnyCDN())->delete($doc->file_url);
        $doc->delete();
        return back()->with('success', 'Document deleted.');
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
