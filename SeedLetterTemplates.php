<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Tenant\LetterTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedLetterTemplates extends Command
{
    protected $signature = 'templates:seed {--tenant= : Specific tenant ID (or "all")}';
    protected $description = 'Seed default letter templates into tenant schemas';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId && $tenantId !== 'all') {
            $companies = Company::where('id', $tenantId)->get();
        } else {
            $companies = Company::all();
        }

        foreach ($companies as $company) {
            $schema = 'tenant_' . $company->id;

            try {
                DB::connection('tenant')->statement("SET search_path TO \"{$schema}\", public");

                $count = 0;
                foreach ($this->templates() as $tpl) {
                    $existing = DB::connection('tenant')->table('letter_templates')
                        ->where('type', $tpl['type'])
                        ->where('is_default', true)
                        ->first();

                    if (!$existing) {
                        DB::connection('tenant')->table('letter_templates')->insert(array_merge($tpl, [
                            'is_default'  => true,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]));
                        $count++;
                    }
                }

                $this->info("  {$company->name}: {$count} templates seeded");
            } catch (\Throwable $e) {
                $this->error("  {$company->name}: " . $e->getMessage());
            }
        }

        DB::connection('tenant')->statement("SET search_path TO public");
        $this->info('Done.');
        return 0;
    }

    protected function templates(): array
    {
        return [

            // ── 1. APPOINTMENT LETTER ──
            [
                'name' => 'Appointment Letter',
                'type' => 'appointment',
                'body' => '<h2 style="text-align:center">APPOINTMENT LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>{{address}}</p>
<p>Dear {{employee_name}},</p>
<p>With reference to your application and subsequent interview, we are pleased to offer you the position of <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department at <strong>{{company_name}}</strong>, effective from <strong>{{date_of_joining}}</strong>.</p>

<h3>Compensation Details</h3>
<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse">
<tr><td>Annual CTC</td><td>{{ctc_annual}}</td></tr>
<tr><td>Basic Salary (Monthly)</td><td>{{basic_salary}}</td></tr>
<tr><td>HRA (Monthly)</td><td>{{hra}}</td></tr>
<tr><td>Special Allowance (Monthly)</td><td>{{special_allowance}}</td></tr>
<tr><td><strong>Total Monthly</strong></td><td><strong>{{monthly_salary}}</strong></td></tr>
</table>

<h3>Terms & Conditions</h3>
<p>1. You will be on probation for a period of 6 months from the date of joining.</p>
<p>2. During the probation period, either party may terminate the employment by giving 15 days written notice.</p>
<p>3. After confirmation, a notice period of 30 days from either side is required.</p>
<p>4. You shall devote your full time and attention to the duties assigned to you.</p>
<p>5. You are expected to maintain strict confidentiality regarding the company\'s business and proprietary information.</p>
<p>6. This offer is contingent upon satisfactory verification of your background and qualifications.</p>

<p>Please sign the duplicate copy of this letter as a token of your acceptance and return it to the HR department.</p>
<p>We look forward to your valuable contribution to the organization.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>
<br><br>
<p><strong>Acceptance</strong></p>
<p>I, {{employee_name}}, accept the above terms and conditions of employment.</p>
<br>
<p>Signature: ________________________ &nbsp;&nbsp; Date: ________________________</p>',
            ],

            // ── 2. CONFIRMATION LETTER ──
            [
                'name' => 'Confirmation Letter',
                'type' => 'confirmation',
                'body' => '<h2 style="text-align:center">CONFIRMATION OF EMPLOYMENT</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>We are pleased to inform you that based on your satisfactory performance during the probation period, your services are confirmed with effect from <strong>{{today_date}}</strong>.</p>
<p>You were appointed as <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department on <strong>{{date_of_joining}}</strong>. Having successfully completed your probationary period, you are now a confirmed employee of <strong>{{company_name}}</strong>.</p>
<p>All other terms and conditions of your employment as mentioned in your appointment letter shall remain unchanged.</p>
<p>We congratulate you and wish you a long and successful career with us.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 3. PROMOTION LETTER ──
            [
                'name' => 'Promotion Letter',
                'type' => 'promotion',
                'body' => '<h2 style="text-align:center">PROMOTION LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}</p>
<p>Dear {{employee_name}},</p>
<p>We are delighted to inform you that in recognition of your outstanding performance and valuable contribution, the management has decided to promote you to the position of <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department with effect from <strong>{{today_date}}</strong>.</p>

<h3>Revised Compensation</h3>
<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse">
<tr><td>Revised Annual CTC</td><td>{{ctc_annual}}</td></tr>
<tr><td>Basic Salary (Monthly)</td><td>{{basic_salary}}</td></tr>
<tr><td>HRA (Monthly)</td><td>{{hra}}</td></tr>
<tr><td>Special Allowance (Monthly)</td><td>{{special_allowance}}</td></tr>
</table>

<p>All other terms and conditions of your employment remain unchanged.</p>
<p>We congratulate you and look forward to your continued growth with the company.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 4. TRANSFER LETTER ──
            [
                'name' => 'Transfer Letter',
                'type' => 'transfer',
                'body' => '<h2 style="text-align:center">TRANSFER LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>This is to inform you that the management has decided to transfer you from the <strong>{{department}}</strong> department to the <strong>___________</strong> department / location with effect from <strong>___________</strong>.</p>
<p>Your designation and compensation shall remain unchanged unless otherwise communicated.</p>
<p>You are requested to hand over all pending work and company assets to your current department head before the effective date of transfer.</p>
<p>Please report to your new department head / location on the date mentioned above.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 5. WARNING LETTER ──
            [
                'name' => 'Warning Letter',
                'type' => 'warning',
                'body' => '<h2 style="text-align:center">WARNING LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>This letter serves as a formal warning regarding <strong>___________________________</strong>.</p>
<p>The above conduct is in violation of the company policies and the standards expected of employees at {{company_name}}. Despite previous verbal counseling on <strong>___________</strong>, the matter has not been rectified.</p>
<p>You are hereby warned and advised to immediately correct the above-mentioned issue. Failure to show improvement may result in further disciplinary action, up to and including termination of employment.</p>
<p>A copy of this letter will be placed in your personnel file.</p>
<p>Please acknowledge receipt of this letter by signing below.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>
<br><br>
<p>Employee Acknowledgement: ________________________ &nbsp;&nbsp; Date: ___________</p>',
            ],

            // ── 6. SHOW CAUSE NOTICE ──
            [
                'name' => 'Show Cause Notice',
                'type' => 'show_cause',
                'body' => '<h2 style="text-align:center">SHOW CAUSE NOTICE</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Subject: Show Cause Notice</p>
<p>Dear {{employee_name}},</p>
<p>It has been brought to our notice that you have <strong>___________________________</strong>.</p>
<p>The above act constitutes a serious violation of company rules and regulations. As per the company policy, such conduct may attract disciplinary proceedings including termination of services.</p>
<p>You are hereby directed to submit your written explanation within <strong>48 hours</strong> of receipt of this notice, explaining why disciplinary action should not be taken against you.</p>
<p>Failure to respond within the stipulated time shall be treated as acceptance of the charge(s) and appropriate action will be initiated accordingly.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 7. SUSPENSION LETTER ──
            [
                'name' => 'Suspension Letter',
                'type' => 'suspension',
                'body' => '<h2 style="text-align:center">SUSPENSION LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>This is to inform you that the management has decided to place you under suspension with immediate effect pending an inquiry into the charges of <strong>___________________________</strong>.</p>
<p>During the period of suspension:</p>
<p>1. You shall not enter the company premises without prior written permission.</p>
<p>2. You shall not leave the city without prior intimation to the management.</p>
<p>3. You shall be entitled to subsistence allowance as per applicable laws.</p>
<p>You are required to cooperate with the inquiry proceedings and make yourself available as and when required.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 8. TERMINATION LETTER ──
            [
                'name' => 'Termination Letter',
                'type' => 'termination',
                'body' => '<h2 style="text-align:center">TERMINATION LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>After careful consideration, the management has decided to terminate your employment with <strong>{{company_name}}</strong> with effect from <strong>{{date_of_leaving}}</strong>.</p>
<p>This decision has been taken due to <strong>___________________________</strong>.</p>
<p>You are required to:</p>
<p>1. Hand over all company property, documents, and access credentials by your last working day.</p>
<p>2. Complete all pending handover formalities with your department head.</p>
<p>3. Clear any outstanding dues to the company.</p>
<p>Your full and final settlement will be processed within 30 days of your last working day in accordance with company policy and applicable laws.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 9. RESIGNATION ACCEPTANCE ──
            [
                'name' => 'Resignation Acceptance Letter',
                'type' => 'resignation_acceptance',
                'body' => '<h2 style="text-align:center">RESIGNATION ACCEPTANCE</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>This is in reference to your resignation letter dated <strong>___________</strong>.</p>
<p>We hereby accept your resignation from the position of <strong>{{designation}}</strong> at <strong>{{company_name}}</strong>. Your last working day will be <strong>{{date_of_leaving}}</strong>.</p>
<p>Please ensure the following before your last working day:</p>
<p>1. Complete handover of all ongoing projects and responsibilities.</p>
<p>2. Return all company property including laptop, access cards, and documents.</p>
<p>3. Clear all pending dues, if any.</p>
<p>Your full and final settlement will be processed within 30 working days of your last working day.</p>
<p>We thank you for your contributions and wish you success in your future endeavors.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 10. RELIEVING LETTER ──
            [
                'name' => 'Relieving Letter',
                'type' => 'relieving',
                'body' => '<h2 style="text-align:center">RELIEVING LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>{{address}}</p>
<p>Dear {{employee_name}},</p>
<p>This is to certify that <strong>{{employee_name}}</strong> (Employee ID: {{employee_id}}) was employed with <strong>{{company_name}}</strong> as <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department from <strong>{{date_of_joining}}</strong> to <strong>{{date_of_leaving}}</strong>.</p>
<p>He/She has been relieved from his/her duties with effect from <strong>{{date_of_leaving}}</strong> and has no pending obligations with the organization.</p>
<p>We wish him/her all the best in future endeavors.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 11. EXPERIENCE LETTER ──
            [
                'name' => 'Experience Letter',
                'type' => 'experience',
                'body' => '<h2 style="text-align:center">EXPERIENCE LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To Whom It May Concern,</p>
<p>This is to certify that <strong>{{employee_name}}</strong> (Employee ID: {{employee_id}}) was employed with <strong>{{company_name}}</strong> from <strong>{{date_of_joining}}</strong> to <strong>{{date_of_leaving}}</strong>.</p>
<p>During this period, he/she held the position of <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department. His/Her last drawn CTC was <strong>{{ctc_annual}}</strong> per annum.</p>
<p>During his/her tenure, he/she demonstrated professionalism, dedication, and was a valued member of our team. His/Her conduct and performance were found to be satisfactory.</p>
<p>We wish him/her all the best in future endeavors.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 12. FULL AND FINAL SETTLEMENT ──
            [
                'name' => 'Full & Final Settlement Letter',
                'type' => 'full_and_final',
                'body' => '<h2 style="text-align:center">FULL AND FINAL SETTLEMENT</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}</p>
<p>Dear {{employee_name}},</p>
<p>This is with reference to your separation from <strong>{{company_name}}</strong> effective <strong>{{date_of_leaving}}</strong>.</p>
<p>Please find below the details of your Full and Final settlement:</p>

<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse">
<tr style="background:#f0f0f0"><th colspan="2"><strong>Earnings</strong></th></tr>
<tr><td>Salary (last month)</td><td>{{monthly_salary}}</td></tr>
<tr><td>Leave Encashment (_____ days)</td><td>___________</td></tr>
<tr><td>Bonus / Incentives</td><td>___________</td></tr>
<tr><td>Gratuity</td><td>___________</td></tr>
<tr><td>Other Earnings</td><td>___________</td></tr>
<tr style="background:#f0f0f0"><th colspan="2"><strong>Deductions</strong></th></tr>
<tr><td>Notice Period Recovery</td><td>___________</td></tr>
<tr><td>Income Tax / TDS</td><td>___________</td></tr>
<tr><td>PF Deduction</td><td>___________</td></tr>
<tr><td>Other Deductions</td><td>___________</td></tr>
<tr style="background:#e8f5e9"><td><strong>Net Payable</strong></td><td><strong>___________</strong></td></tr>
</table>

<p>The above amount will be credited to your bank account within 30 working days.</p>
<p>By signing below, you acknowledge that the above settlement is complete and final and you have no further claims of any nature against the company.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>
<br>
<p>Employee Signature: ________________________ &nbsp;&nbsp; Date: ___________</p>',
            ],

            // ── 13. INCREMENT LETTER ──
            [
                'name' => 'Increment / Appraisal Letter',
                'type' => 'increment',
                'body' => '<h2 style="text-align:center">ANNUAL INCREMENT LETTER</h2>
<p>Date: {{today_date}}</p>
<p><strong>CONFIDENTIAL</strong></p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>We are pleased to inform you that based on your performance review for the year {{current_year}}, the management has decided to revise your compensation as follows:</p>

<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse">
<tr><td>Previous Annual CTC</td><td>___________</td></tr>
<tr><td>Revised Annual CTC</td><td>{{ctc_annual}}</td></tr>
<tr><td>Increment Amount</td><td>___________</td></tr>
<tr><td>Increment Percentage</td><td>___________%</td></tr>
<tr><td>Effective From</td><td>___________</td></tr>
</table>

<h3>Revised Monthly Breakup</h3>
<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse">
<tr><td>Basic Salary</td><td>{{basic_salary}}</td></tr>
<tr><td>HRA</td><td>{{hra}}</td></tr>
<tr><td>Special Allowance</td><td>{{special_allowance}}</td></tr>
</table>

<p>We appreciate your dedication and look forward to your continued contributions.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 14. BONUS LETTER ──
            [
                'name' => 'Bonus Letter',
                'type' => 'bonus',
                'body' => '<h2 style="text-align:center">BONUS LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>We are happy to inform you that in recognition of your contribution and the company\'s performance, the management has decided to award you a bonus of <strong>₹___________</strong> for the year {{current_year}}.</p>
<p>The bonus amount will be credited to your salary account along with your next month\'s salary.</p>
<p>We appreciate your hard work and commitment. Keep up the excellent performance!</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 15. NOC (NO OBJECTION CERTIFICATE) ──
            [
                'name' => 'No Objection Certificate (NOC)',
                'type' => 'noc',
                'body' => '<h2 style="text-align:center">NO OBJECTION CERTIFICATE</h2>
<p>Date: {{today_date}}</p>
<p>To Whom It May Concern,</p>
<p>This is to certify that <strong>{{employee_name}}</strong> (Employee ID: {{employee_id}}) is / was an employee of <strong>{{company_name}}</strong> in the capacity of <strong>{{designation}}</strong>.</p>
<p>We have no objection to his/her <strong>___________________________</strong>.</p>
<p>This certificate is issued at the request of the employee for the purpose of <strong>___________________________</strong>.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

            // ── 16. INTERNSHIP OFFER LETTER ──
            [
                'name' => 'Internship Offer Letter',
                'type' => 'internship',
                'body' => '<h2 style="text-align:center">INTERNSHIP OFFER LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>{{address}}</p>
<p>Dear {{employee_name}},</p>
<p>We are pleased to offer you an internship at <strong>{{company_name}}</strong> in the <strong>{{department}}</strong> department.</p>

<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse">
<tr><td>Position</td><td>{{designation}} (Intern)</td></tr>
<tr><td>Duration</td><td>___________ to ___________</td></tr>
<tr><td>Stipend</td><td>{{monthly_salary}} per month</td></tr>
<tr><td>Working Hours</td><td>___________ to ___________</td></tr>
<tr><td>Reporting To</td><td>___________</td></tr>
</table>

<p>During the internship period, you will gain practical exposure and be assigned projects relevant to your field of study. Upon successful completion, you will be issued an Internship Completion Certificate.</p>
<p>This internship does not guarantee permanent employment with the company.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>
<br>
<p>I accept the above terms.<br>Signature: ________________________ &nbsp;&nbsp; Date: ___________</p>',
            ],

            // ── 17. PROBATION COMPLETION LETTER ──
            [
                'name' => 'Probation Completion Letter',
                'type' => 'probation_completion',
                'body' => '<h2 style="text-align:center">PROBATION COMPLETION LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>This is to inform you that your probation period of 6 months, which commenced on <strong>{{date_of_joining}}</strong>, has now been completed.</p>
<p>We are pleased to confirm that your performance during the probation period has been satisfactory, and your services are hereby confirmed with immediate effect.</p>
<p>Post confirmation, the notice period applicable to you shall be 30 days from either side.</p>
<p>All other terms and conditions of your appointment letter remain unchanged.</p>
<p>We look forward to your continued growth and success with <strong>{{company_name}}</strong>.</p>
<br>
<p>For <strong>{{company_name}}</strong></p>
<br><br>
<p>________________________<br>Authorized Signatory</p>',
            ],

        ];
    }
}
