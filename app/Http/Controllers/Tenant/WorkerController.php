<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkerController extends Controller
{
    public function index(Request $request): Response
    {
        $workers = Worker::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%' . $request->input('q') . '%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Tenant/Workers/Index', [
            'workers' => $workers,
            'filters' => $request->only(['q', 'status']),
            'totals'  => [
                'total'  => Worker::count(),
                'active' => Worker::where('status', 'active')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        $lastWorker = Worker::orderByDesc('id')->first();
        $nextId = 'WRK-' . str_pad((string) (($lastWorker ? (int) str_replace('WRK-', '', $lastWorker->worker_id) : 0) + 1), 3, '0', STR_PAD_LEFT);

        return Inertia::render('Tenant/Workers/Create', [
            'nextWorkerId' => $nextId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'worker_id'               => ['required', 'string', 'max:20', 'unique:workers,worker_id'],
            'name'                    => ['required', 'string', 'max:120'],
            'father_husband_name'     => ['nullable', 'string', 'max:120'],
            'date_of_birth'           => ['nullable', 'date'],
            'age'                     => ['nullable', 'integer', 'min:14', 'max:100'],
            'permanent_address'       => ['nullable', 'string', 'max:500'],
            'local_address'           => ['nullable', 'string', 'max:500'],
            'education'               => ['nullable', 'string', 'max:200'],
            'technical_qualification' => ['nullable', 'string', 'max:200'],
            'languages'               => ['nullable', 'string', 'max:200'],
            'mobile'                  => ['nullable', 'string', 'max:20'],
            'pan_number'              => ['nullable', 'string', 'max:10'],
            'aadhaar_number'          => ['nullable', 'string', 'max:12'],
            'pf_uan'                  => ['nullable', 'string', 'max:20'],
            'post_applied'            => ['nullable', 'string', 'max:100'],
            'post_held'               => ['nullable', 'string', 'max:100'],
            'appointment_from'        => ['nullable', 'date'],
            'appointment_to'          => ['nullable', 'date'],
            'appointment_type'        => ['nullable', 'string', 'in:temporary,permanent,contract'],
            'daily_wage'              => ['nullable', 'numeric', 'min:0'],
            'monthly_wage'            => ['nullable', 'numeric', 'min:0'],
            'payment_mode'            => ['nullable', 'string', 'in:daily,monthly,piece_rate'],
            'pf_applicable'           => ['nullable', 'boolean'],
            'esi_applicable'          => ['nullable', 'boolean'],
            'pf_number'               => ['nullable', 'string', 'max:30'],
            'esi_number'              => ['nullable', 'string', 'max:30'],
            'experience'              => ['nullable', 'array'],
            'references'              => ['nullable', 'array'],
            'bank_name'               => ['nullable', 'string', 'max:80'],
            'bank_account_number'     => ['nullable', 'string', 'max:30'],
            'bank_ifsc'               => ['nullable', 'string', 'max:15'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
        ]);

        Worker::create($validated);

        $slug = $request->route('tenant');
        return redirect()->route('tenant.workers.index', ['tenant' => $slug])
            ->with('success', 'कर्मचारी जोड़ा गया। (Worker added)');
    }

    public function show(Request $request, string $tenant, Worker $worker): Response
    {
        return Inertia::render('Tenant/Workers/Show', [
            'worker' => $worker,
        ]);
    }

    public function edit(Request $request, string $tenant, Worker $worker): Response
    {
        return Inertia::render('Tenant/Workers/Edit', [
            'worker' => $worker,
        ]);
    }

    public function update(Request $request, string $tenant, Worker $worker): RedirectResponse
    {
        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:120'],
            'father_husband_name'     => ['nullable', 'string', 'max:120'],
            'date_of_birth'           => ['nullable', 'date'],
            'age'                     => ['nullable', 'integer', 'min:14', 'max:100'],
            'permanent_address'       => ['nullable', 'string', 'max:500'],
            'local_address'           => ['nullable', 'string', 'max:500'],
            'education'               => ['nullable', 'string', 'max:200'],
            'technical_qualification' => ['nullable', 'string', 'max:200'],
            'languages'               => ['nullable', 'string', 'max:200'],
            'mobile'                  => ['nullable', 'string', 'max:20'],
            'pan_number'              => ['nullable', 'string', 'max:10'],
            'aadhaar_number'          => ['nullable', 'string', 'max:12'],
            'pf_uan'                  => ['nullable', 'string', 'max:20'],
            'post_applied'            => ['nullable', 'string', 'max:100'],
            'post_held'               => ['nullable', 'string', 'max:100'],
            'appointment_from'        => ['nullable', 'date'],
            'appointment_to'          => ['nullable', 'date'],
            'appointment_type'        => ['nullable', 'string', 'in:temporary,permanent,contract'],
            'daily_wage'              => ['nullable', 'numeric', 'min:0'],
            'monthly_wage'            => ['nullable', 'numeric', 'min:0'],
            'payment_mode'            => ['nullable', 'string', 'in:daily,monthly,piece_rate'],
            'pf_applicable'           => ['nullable', 'boolean'],
            'esi_applicable'          => ['nullable', 'boolean'],
            'pf_number'               => ['nullable', 'string', 'max:30'],
            'esi_number'              => ['nullable', 'string', 'max:30'],
            'experience'              => ['nullable', 'array'],
            'references'              => ['nullable', 'array'],
            'status'                  => ['nullable', 'string', 'in:active,terminated,absconded,completed'],
            'date_of_leaving'         => ['nullable', 'date'],
            'reason_leaving'          => ['nullable', 'string', 'max:200'],
            'bank_name'               => ['nullable', 'string', 'max:80'],
            'bank_account_number'     => ['nullable', 'string', 'max:30'],
            'bank_ifsc'               => ['nullable', 'string', 'max:15'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
        ]);

        $worker->update($validated);

        $slug = $request->route('tenant');
        return redirect()->route('tenant.workers.show', ['tenant' => $slug, 'worker' => $worker->id])
            ->with('success', 'कर्मचारी अपडेट किया गया। (Worker updated)');
    }

    public function destroy(Request $request, string $tenant, Worker $worker): RedirectResponse
    {
        $worker->delete();

        $slug = $request->route('tenant');
        return redirect()->route('tenant.workers.index', ['tenant' => $slug])
            ->with('success', 'कर्मचारी हटाया गया। (Worker removed)');
    }
}
