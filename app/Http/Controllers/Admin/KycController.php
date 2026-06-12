<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyKyc;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KycController extends Controller
{
    public function index()
    {
        $kycs = CompanyKyc::with('company:id,name,slug')
            ->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'pending' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
            ->orderByDesc('submitted_at')
            ->paginate(20);

        return Inertia::render('Admin/Companies/KycList', ['kycs' => $kycs]);
    }

    public function approve(int $id)
    {
        CompanyKyc::findOrFail($id)->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
        return back()->with('success', 'KYC approved.');
    }

    public function reject(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        CompanyKyc::findOrFail($id)->update([
            'status'           => 'rejected',
            'reviewed_by'      => auth()->id(),
            'reviewed_at'      => now(),
            'rejection_reason' => $request->reason,
        ]);
        return back()->with('success', 'KYC rejected.');
    }
}
