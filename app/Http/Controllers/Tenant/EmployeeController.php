<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            $employees = Employee::query()
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('department'), fn ($q) => $q->where('department', $request->input('department')))
                ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where(function ($qq) use ($request) {
                    $n = '%' . $request->string('q') . '%';
                    $qq->where('first_name', 'ilike', $n)
                       ->orWhere('last_name', 'ilike', $n)
                       ->orWhere('employee_id', 'ilike', $n)
                       ->orWhere('email', 'ilike', $n)
                       ->orWhere('designation', 'ilike', $n);
                }))
                ->latest()
                ->paginate(25)
                ->withQueryString();

            $departments = Employee::distinct()->whereNotNull('department')->pluck('department')->toArray();
            $totals = [
                'total'    => Employee::count(),
                'active'   => Employee::where('status', 'active')->count(),
                'on_notice'=> Employee::where('status', 'on_notice')->count(),
            ];
        } catch (\Throwable $e) {
            $employees = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
            $departments = [];
            $totals = ['total' => 0, 'active' => 0, 'on_notice' => 0];
        }

        return Inertia::render('Tenant/HR/Employees/Index', [
            'employees'   => $employees,
            'departments' => $departments,
            'totals'      => $totals,
            'filters'     => $request->only(['status', 'department', 'q']),
        ]);
    }

    public function create(): Response
    {
        $nextId = 'EMP-' . str_pad((string) (Employee::max('id') + 1), 3, '0', STR_PAD_LEFT);

        return Inertia::render('Tenant/HR/Employees/Create', [
            'nextEmployeeId' => $nextId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id'     => ['required', 'string', 'max:20', Rule::unique('employees', 'employee_id')],
            'first_name'      => ['required', 'string', 'max:80'],
            'last_name'       => ['nullable', 'string', 'max:80'],
            'email'           => ['nullable', 'email', 'max:180'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'date_of_birth'   => ['nullable', 'date'],
            'gender'          => ['nullable', 'in:male,female,other'],
            'designation'     => ['nullable', 'string', 'max:100'],
            'department'      => ['nullable', 'string', 'max:100'],
            'date_of_joining' => ['nullable', 'date'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract,intern'],
            'status'          => ['nullable', 'in:active,on_notice,terminated,resigned'],
            'ctc_annual'      => ['nullable', 'numeric', 'min:0'],
            'basic_salary'    => ['nullable', 'numeric', 'min:0'],
            'hra'             => ['nullable', 'numeric', 'min:0'],
            'special_allowance'=> ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'bank_name'       => ['nullable', 'string', 'max:80'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc'       => ['nullable', 'string', 'max:15'],
            'pan_number'      => ['nullable', 'string', 'max:10'],
            'aadhaar_number'  => ['nullable', 'string', 'max:12'],
            'uan_number'      => ['nullable', 'string', 'max:20'],
            'esi_number'      => ['nullable', 'string', 'max:20'],
            'address'         => ['nullable', 'string'],
            'city'            => ['nullable', 'string', 'max:60'],
            'state'           => ['nullable', 'string', 'max:60'],
            'pincode'         => ['nullable', 'string', 'max:10'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:30'],
            'notes'           => ['nullable', 'string'],
        ]);

        Employee::create(array_merge($validated, [
            'hra'               => $validated['hra'] ?? 0,
            'special_allowance' => $validated['special_allowance'] ?? 0,
            'other_allowance'   => $validated['other_allowance'] ?? 0,
            'basic_salary'      => $validated['basic_salary'] ?? 0,
            'ctc_annual'        => $validated['ctc_annual'] ?? 0,
        ]));

        $slug = window_slug();
        return redirect()->route('tenant.hr.employees', ['tenant' => $slug])
            ->with('success', 'Employee added successfully.');
    }

    public function show(Request $request, string $tenant, string $id): Response
    {
        $employee = Employee::with(['letters', 'documents'])->findOrFail($id);

        return Inertia::render('Tenant/HR/Employees/Show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Request $request, string $tenant, string $id): Response
    {
        $employee = Employee::findOrFail($id);

        return Inertia::render('Tenant/HR/Employees/Edit', [
            'employee' => $employee,
        ]);
    }

    public function update(Request $request, string $tenant, string $id): RedirectResponse
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'first_name'      => ['required', 'string', 'max:80'],
            'last_name'       => ['nullable', 'string', 'max:80'],
            'email'           => ['nullable', 'email', 'max:180'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'date_of_birth'   => ['nullable', 'date'],
            'gender'          => ['nullable', 'in:male,female,other'],
            'designation'     => ['nullable', 'string', 'max:100'],
            'department'      => ['nullable', 'string', 'max:100'],
            'date_of_joining' => ['nullable', 'date'],
            'date_of_leaving' => ['nullable', 'date'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract,intern'],
            'status'          => ['nullable', 'in:active,on_notice,terminated,resigned'],
            'ctc_annual'      => ['nullable', 'numeric', 'min:0'],
            'basic_salary'    => ['nullable', 'numeric', 'min:0'],
            'hra'             => ['nullable', 'numeric', 'min:0'],
            'special_allowance'=> ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'bank_name'       => ['nullable', 'string', 'max:80'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc'       => ['nullable', 'string', 'max:15'],
            'pan_number'      => ['nullable', 'string', 'max:10'],
            'aadhaar_number'  => ['nullable', 'string', 'max:12'],
            'uan_number'      => ['nullable', 'string', 'max:20'],
            'esi_number'      => ['nullable', 'string', 'max:20'],
            'address'         => ['nullable', 'string'],
            'city'            => ['nullable', 'string', 'max:60'],
            'state'           => ['nullable', 'string', 'max:60'],
            'pincode'         => ['nullable', 'string', 'max:10'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:30'],
            'notes'           => ['nullable', 'string'],
        ]);

        $employee->update($validated);

        return redirect()->route('tenant.hr.employees.show', ['tenant' => $tenant, 'id' => $employee->id])
            ->with('success', 'Employee updated.');
    }
}

function window_slug(): string
{
    return request()->route('tenant') ?? '';
}
