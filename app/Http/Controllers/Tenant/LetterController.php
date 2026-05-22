<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Letter;
use App\Models\Tenant\LetterTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LetterController extends Controller
{
    protected const ALL_TYPES = 'appointment,confirmation,promotion,transfer,warning,show_cause,suspension,termination,resignation_acceptance,relieving,experience,full_and_final,increment,bonus,noc,internship,probation_completion,worker_appointment,custom';

    /* ── Templates ─────────────────────────────── */

    public function templates(): Response
    {
        try {
            $templates = LetterTemplate::orderBy('type')->orderBy('name')->get();
        } catch (\Throwable $e) {
            $templates = collect();
        }

        return Inertia::render('Tenant/HR/Letters/Templates', [
            'templates'    => $templates,
            'placeholders' => LetterTemplate::placeholders(),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:' . self::ALL_TYPES],
            'body' => ['required', 'string'],
        ]);

        LetterTemplate::create($validated);

        return back()->with('success', 'Template created.');
    }

    public function updateTemplate(Request $request, string $tenant, string $id): RedirectResponse
    {
        $template = LetterTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:' . self::ALL_TYPES],
            'body' => ['required', 'string'],
        ]);

        $template->update($validated);

        return back()->with('success', 'Template updated.');
    }

    public function destroyTemplate(Request $request, string $tenant, string $id): RedirectResponse
    {
        LetterTemplate::findOrFail($id)->delete();
        return back()->with('success', 'Template deleted.');
    }

    /* ── Letters ──────────────────────────────── */

    public function create(Request $request): Response
    {
        $employees = Employee::where('status', 'active')->orderBy('first_name')->get(['id', 'employee_id', 'first_name', 'last_name', 'designation', 'department']);
        $templates = LetterTemplate::orderBy('type')->get(['id', 'name', 'type', 'body']);

        return Inertia::render('Tenant/HR/Letters/Create', [
            'employees'    => $employees,
            'templates'    => $templates,
            'placeholders' => LetterTemplate::placeholders(),
            'selectedEmployee' => $request->filled('employee_id')
                ? Employee::find($request->input('employee_id'))
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id'       => ['required', 'exists:employees,id'],
            'letter_template_id'=> ['nullable', 'exists:letter_templates,id'],
            'type'              => ['required', 'in:' . self::ALL_TYPES],
            'title'             => ['required', 'string', 'max:200'],
            'body'              => ['required', 'string'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $company = app('current_company');

        // Replace placeholders — works for both regular and worker-type templates
        $body = $this->replacePlaceholders($validated['body'], $employee, $company);

        $letter = Letter::create([
            'employee_id'        => $employee->id,
            'letter_template_id' => $validated['letter_template_id'] ?? null,
            'type'               => $validated['type'],
            'title'              => $validated['title'],
            'body'               => $body,
            'status'             => 'draft',
        ]);

        $slug = request()->route('tenant') ?? '';
        return redirect()->route('tenant.hr.letters.show', ['tenant' => $slug, 'id' => $letter->id])
            ->with('success', 'Letter created.');
    }

    public function show(Request $request, string $tenant, string $id): Response
    {
        $letter = Letter::with('employee')->findOrFail($id);

        return Inertia::render('Tenant/HR/Letters/Show', [
            'letter'    => $letter,
            'company'   => app('current_company'),
        ]);
    }

    public function update(Request $request, string $tenant, string $id): RedirectResponse
    {
        $letter = Letter::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'body'  => ['sometimes', 'string'],
            'status'=> ['sometimes', 'in:draft,issued'],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'issued') {
            $validated['issued_at'] = now();
            $validated['issued_by'] = auth()->id();
        }

        $letter->update($validated);

        return back()->with('success', 'Letter updated.');
    }

    public function destroy(Request $request, string $tenant, string $id): RedirectResponse
    {
        Letter::findOrFail($id)->delete();
        $slug = request()->route('tenant') ?? '';
        return redirect()->route('tenant.hr.employees', ['tenant' => $slug])
            ->with('success', 'Letter deleted.');
    }

    /* ── Helpers ──────────────────────────────── */

    /**
     * Replace all placeholders — handles both employee-style and worker-style templates.
     * Worker templates use the same employee data but mapped to Hindi field names.
     */
    protected function replacePlaceholders(string $body, Employee $employee, $company): string
    {
        $fmt = fn ($v) => '₹' . number_format((float) $v, 2);
        $dateFmt = fn ($d) => $d ? $d->format('d M Y') : '___________';
        $dateFmtSlash = fn ($d) => $d ? $d->format('d/m/Y') : '___________';

        $fullAddress = implode(', ', array_filter([$employee->address, $employee->city, $employee->state, $employee->pincode]));

        $replacements = [
            // Standard employee placeholders
            '{{employee_name}}'      => $employee->full_name,
            '{{employee_id}}'        => $employee->employee_id,
            '{{designation}}'        => $employee->designation ?? '___________',
            '{{department}}'         => $employee->department ?? '___________',
            '{{date_of_joining}}'    => $dateFmt($employee->date_of_joining),
            '{{date_of_leaving}}'    => $dateFmt($employee->date_of_leaving),
            '{{ctc_annual}}'         => $fmt($employee->ctc_annual),
            '{{basic_salary}}'       => $fmt($employee->basic_salary),
            '{{hra}}'                => $fmt($employee->hra),
            '{{special_allowance}}'  => $fmt($employee->special_allowance),
            '{{monthly_salary}}'     => $fmt($employee->basic_salary + $employee->hra + $employee->special_allowance + $employee->other_allowance),
            '{{email}}'              => $employee->email ?? '___________',
            '{{phone}}'              => $employee->phone ?? '___________',
            '{{address}}'            => $fullAddress ?: '___________',
            '{{pan_number}}'         => $employee->pan_number ?? '___________',
            '{{company_name}}'       => $company->name ?? '___________',
            '{{today_date}}'         => now()->format('d M Y'),
            '{{current_year}}'       => now()->format('Y'),

            // Worker-style placeholders (mapped from same employee fields)
            '{{worker_name}}'               => $employee->full_name,
            '{{worker_id}}'                 => $employee->employee_id,
            '{{father_husband_name}}'       => $employee->emergency_contact_name ?? '___________',
            '{{date_of_birth}}'             => $dateFmtSlash($employee->date_of_birth),
            '{{age}}'                       => $employee->date_of_birth ? (string) $employee->date_of_birth->age : '______',
            '{{permanent_address}}'         => $fullAddress ?: '___________',
            '{{local_address}}'             => $fullAddress ?: '___________',
            '{{education}}'                 => '___________',
            '{{technical_qualification}}'   => '___________',
            '{{languages}}'                 => '___________',
            '{{mobile}}'                    => $employee->phone ?? '___________',
            '{{aadhaar_number}}'            => $employee->aadhaar_number ?? '___________',
            '{{pf_uan}}'                    => $employee->uan_number ?? '___________',
            '{{post_applied}}'              => $employee->designation ?? '___________',
            '{{post_held}}'                 => $employee->designation ?? '___________',
            '{{appointment_from}}'          => $dateFmtSlash($employee->date_of_joining),
            '{{appointment_to}}'            => '___________',
            '{{daily_wage}}'                => $fmt(($employee->basic_salary + $employee->hra + $employee->special_allowance + $employee->other_allowance) / 26),
            '{{monthly_wage}}'              => $fmt($employee->basic_salary + $employee->hra + $employee->special_allowance + $employee->other_allowance),
            '{{experience_table}}'          => '<tr><td colspan="7" style="padding:8px;border:1px solid #999;text-align:center">—</td></tr>',
            '{{references_table}}'          => '<tr><td colspan="4" style="padding:8px;border:1px solid #999;text-align:center">—</td></tr>',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $body);
    }
}
