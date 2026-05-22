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

            // ── 18. WORKER APPOINTMENT LETTER (Bio-Data + Temporary Appointment — Hindi + English) ──
            [
                'name' => 'Worker Appointment Letter',
                'type' => 'worker_appointment',
                'body' => '<div style="font-family:serif;max-width:700px;margin:0 auto">

<!-- ═══════════ PAGE 1: व्यक्तिगत विवरण फार्म / BIO-DATA FORM ═══════════ -->
<div style="padding:20px;border:2px solid #333;margin-bottom:30px">

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
<div style="border:1px solid #999;padding:10px;font-size:13px;line-height:2">
<div><em>Mobile No.</em> <strong>{{mobile}}</strong></div>
<div><em>PAN No.</em> <strong>{{pan_number}}</strong></div>
<div><em>Adhar Card No.</em> <strong>{{aadhaar_number}}</strong></div>
<div><em>PF No. / UAN</em> <strong>{{pf_uan}}</strong></div>
</div>
<div style="text-align:center">
<h2 style="margin:0">व्यक्तिगत विवरण फार्म</h2>
<h2 style="margin:0">BIO-DATA FORM</h2>
</div>
<div style="width:80px;height:100px;border:1px solid #999;display:flex;align-items:center;justify-content:center;font-size:10px;color:#999">Photo</div>
</div>

<p style="margin-bottom:15px"><strong>अभ्यर्थिक पद</strong> Post Applied for <strong>{{post_applied}}</strong></p>

<table style="width:100%;border-collapse:collapse;font-size:14px" cellpadding="6">
<tr>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>1.</strong> नाम साफ अक्षरो मे<br><small>Name in Capital Letters</small></td>
<td style="border-bottom:1px solid #ccc;padding:8px 4px" colspan="2"><strong style="font-size:16px;text-transform:uppercase">{{worker_name}}</strong></td>
</tr>
<tr>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>2.</strong> पिता/पति का नाम<br><small>Father\'s/Husband\'s Name</small></td>
<td style="border-bottom:1px solid #ccc;padding:8px 4px" colspan="2"><strong>{{father_husband_name}}</strong></td>
</tr>
<tr>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>3.</strong> जन्म तिथि<br><small>Date of Birth</small></td>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>{{date_of_birth}}</strong></td>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>3(a)</strong> आयु Age <strong>{{age}}</strong></td>
</tr>
<tr>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>4.</strong> स्थाई पता<br><small>Permanent Address</small></td>
<td style="border-bottom:1px solid #ccc;padding:8px 4px" colspan="2">{{permanent_address}}</td>
</tr>
<tr>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>5.</strong> स्थानीय पता<br><small>Local Address</small></td>
<td style="border-bottom:1px solid #ccc;padding:8px 4px" colspan="2">{{local_address}}</td>
</tr>
<tr>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>6.</strong> शैक्षणिक अहर्ताएं<br><small>Educational Qualifications</small></td>
<td style="border-bottom:1px solid #ccc;padding:8px 4px" colspan="2">{{education}}</td>
</tr>
<tr>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>7.</strong> तकनीकी अहर्ताएं<br><small>Technical Qualifications</small></td>
<td style="border-bottom:1px solid #ccc;padding:8px 4px" colspan="2">{{technical_qualification}}</td>
</tr>
<tr>
<td style="border-bottom:1px solid #ccc;padding:8px 4px"><strong>8.</strong> भाषाएं (लिखना एवं पढ़ना)<br><small>Languages Read &amp; Write</small></td>
<td style="border-bottom:1px solid #ccc;padding:8px 4px" colspan="2">{{languages}}</td>
</tr>
</table>

<h3 style="margin-top:20px">9. अनुभव / Experience</h3>
<table style="width:100%;border-collapse:collapse;font-size:12px">
<thead>
<tr style="background:#f5f5f5">
<th style="border:1px solid #999;padding:6px">क्रमांक<br>Sl.No.</th>
<th style="border:1px solid #999;padding:6px">नियोक्ता का नाम व पता<br>Name of Employer &amp; Address</th>
<th style="border:1px solid #999;padding:6px">भारित पद<br>Post held</th>
<th style="border:1px solid #999;padding:6px">अवधि से<br>From</th>
<th style="border:1px solid #999;padding:6px">तक<br>To</th>
<th style="border:1px solid #999;padding:6px">प्राप्त वेतन<br>Salary Drawn</th>
<th style="border:1px solid #999;padding:6px">छोड़ने का कारण<br>Reason for Leaving</th>
</tr>
</thead>
<tbody>
{{experience_table}}
</tbody>
</table>

<h3 style="margin-top:20px">10. दो परिचित व्यक्तियों का हवाला / Refernces of Two known persons</h3>
<table style="width:100%;border-collapse:collapse;font-size:12px">
<thead>
<tr style="background:#f5f5f5">
<th style="border:1px solid #999;padding:6px">#</th>
<th style="border:1px solid #999;padding:6px">नाम / Name</th>
<th style="border:1px solid #999;padding:6px">पता / Address</th>
<th style="border:1px solid #999;padding:6px">पद / Designation</th>
</tr>
</thead>
<tbody>
{{references_table}}
</tbody>
</table>

<div style="text-align:right;margin-top:30px">
<p>________________________</p>
<p>अभ्यर्थी के हस्ताक्षर<br>Signature of Applicant</p>
</div>

</div>

<!-- ═══════════ PAGE 2: DECLARATION / घोषणा ═══════════ -->
<div style="padding:20px;border:2px solid #333;margin-bottom:30px">

<h2 style="text-align:center">DECLARATION</h2>

<p style="font-size:13px">I, <strong>{{worker_name}}</strong>, do hereby solemnly declare that the information given in this Form of Employment is true to the best of my knowledge and belief. In case if any information is found to be incorrect at any time, I shall be liable to be terminated from the services of the Establishment.</p>

<p style="font-size:13px">I am not suffering from any disease which may render me unfit for the post applied for or affect my efficiency of work.</p>

<p style="font-size:13px">I further declare that I have read the standing orders/conditions of service and the same have been explained to me. I have fully understood them and undertake to abide by them. I have also not been convicted by any court of law.</p>

<div style="display:flex;justify-content:space-between;margin-top:30px">
<div>Date: ___________</div>
<div>Signature of applicant: ________________________</div>
</div>

<hr style="margin:25px 0">

<h3 style="text-align:center">घोषणा</h3>

<p style="font-size:13px">मैं <strong>{{worker_name}}</strong> एतद् द्वारा निष्ठापूर्वक विश्वास दिलाता हूं और घोषणा करता हूं कि मेरे द्वारा दिए गए उपरोक्त तथ्य तथा जानकारी मेरे अच्छे विवेक और विश्वास के अनुसार सत्य तथा सही है। मैं यह भी पुष्टि करता हूं कि मैने किसी भी तथ्य को छुपाने की कोशिश नही की है। मैं यह भी समझता हूं तथा स्वीकार करता हूं कि यदि कोई जानकारी गलत पाई गई या मैने इस फार्म मे मांगी गई कोई जानकारी नही दी तो मेरी सेवाएं बिना किसी सूचना के समाप्त की जा सकती है।</p>

<p style="font-size:13px">मैं ऐसे किसी भी रोग से ग्रस्त नही हूं जिससे कि संस्थान मे मेरे कार्य-कुशलता से बाधा हो।</p>

<p style="font-size:13px">मैं यह भी समझता हूं तथा स्वीकार करता हूं कि मेरी सेवाएं कम्पनी के वर्तमान सेवा नियमो, उनमे समय-समय पर किए संशोधनो तथा मेरी संस्थान मे नियुक्ति के स्थान पर मुझ पर लागू कम्पनी के प्रमाणित स्थाई आदेशों के अधीन रहेगी। मैं किसी भी अदालत द्वारा दोषी करार नही दिया गया हूं।</p>

<div style="display:flex;justify-content:space-between;margin-top:30px">
<div>स्थान ___________</div>
<div>तारीख {{today_date}}</div>
<div>अभ्यर्थी के हस्ताक्षर ________________________</div>
</div>

<hr style="margin:25px 0">

<h3 style="text-align:center">FOR OFFICE USE ONLY / कार्यालय प्रयोग हेतु</h3>

<p>Appointment sanctioned / Not sanctioned<br>नियुक्ति मंजूर की गई / मंजूर नहीं की गई।</p>
<div style="display:flex;justify-content:space-between;margin-top:15px">
<div>Date: ___________</div>
<div>Recommending Authority / सिफारिश वाले अधिकारी: ________________________</div>
</div>

<h4 style="text-align:center;margin-top:20px">Appointment accepted / नियुक्ति स्वीकृति</h4>
<div style="display:flex;justify-content:space-between;margin-top:15px">
<div>Date: ___________</div>
<div>Accepting Officer / स्वीकृति अधिकारी: ________________________</div>
</div>

</div>

<!-- ═══════════ PAGE 3: TEMPORARY APPOINTMENT (English) ═══════════ -->
<div style="padding:20px;border:2px solid #333;margin-bottom:30px">

<p>To,<br>
Shri <strong>{{worker_name}}</strong><br>
Permanent Address: {{permanent_address}}<br>
Temporary Address: {{local_address}}</p>

<h2 style="text-align:center;margin:20px 0">Subject: TEMPORARY APPOINTMENT</h2>

<p>Dear Sir,</p>

<p>With reference to your application dated <strong>{{today_date}}</strong> for the post of <strong>{{post_applied}}</strong> and your interview in this connection with the undersigned on management is pleased to appoint you on the post of <strong>{{post_held}}</strong> in our organisation on the following terms and conditions:—</p>

<ol style="font-size:13px;line-height:1.8">
<li>You are being appointed on the information details in your application which forms a part of your service contract. In case of any omission, exaggeration, concealment or misrepresentation in the said application, your services can be terminated without making any reference to you and in that event you shall have no claim against the management of any kind whatsoever.</li>

<li>The aforesaid post offered to you is to be filled up due to temporary pressure of work/exigencies of work/leave vacancy and this period may last upto <strong>{{appointment_to}}</strong> when your appointment will automatically come to end without any notice or reason or any payment in lieu thereof.</li>

<li>You will not have any lieu on the post on which you are being appointed nor will you have any claim to be appointed against a permanent or regular vacancy if so occurs at any time.</li>

<li>Our business is based on certain orders and contracts and in view of the same and also in case your work and conduct found not satisfactory, your service can also be terminated without assigning any reason before the expiry of the period mentioned above. In case of your termination, you shall be paid only Earned Wages and you shall have no claim against us of any kind whatsoever except the Earned Wages.</li>

<li>Your appointment shall be valid from <strong>{{appointment_from}}</strong>.</li>

<li>(i) You shall be daily/monthly rated employee and shall be paid Rs. <strong>{{daily_wage}}</strong> per day / <strong>{{monthly_wage}}</strong> per month consolidated.<br><br>
(ii) You shall be piece rated employee and shall be paid on piece rate bases as agreed from time to time.</li>

<li>You shall not be entitled to any benefits and privileges which are being extended to Permanent Employees of the Establishment.</li>

<li>You can be employed in any alternative post carrying identical rate of wages if so warranted by the circumstances and in that event the decision of the management shall be final and binding on you. You can also be employed at the sites where the works of the company are in progress or may come up later on the same employments and terms and conditions.</li>

<li>Continued absence for 3 (three) days from the duty without express permission in writing of the management will tantamount to voluntary abandonment of service on your own part and your name will be struck off the Muster Rolls without giving any notice.</li>
</ol>

<p>In case the terms and conditions of your employment detailed above are acceptable to you, you are required to please signify your acceptance on the duplicate copy of this letter contents of which have been read over and explained to you.</p>

<div style="text-align:right;margin-top:30px">
<p>For Managing Director/Manager/Partner/Prop.</p>
<p>________________________</p>
<p>For <strong>{{company_name}}</strong></p>
</div>

<hr style="margin:30px 0">

<p>The contents of the letter detailed above have been read by me and its meaning explained to me, I accept the appointment. I have also received a copy of this letter.</p>

<div style="display:flex;justify-content:space-between;margin-top:20px">
<div>Date: ___________</div>
<div style="text-align:right">
Signature: ________________________<br>
Name: <strong>{{worker_name}}</strong><br>
Address: {{permanent_address}}
</div>
</div>

<p style="font-size:11px;margin-top:20px;color:#666"><strong>Note:</strong> In case of any difference in opinion over the interpretation of the above clause, English version shall be treated as authentic.</p>

</div>

<!-- ═══════════ PAGE 4: अस्थाई नियुक्ति (Hindi) ═══════════ -->
<div style="padding:20px;border:2px solid #333">

<p>सेवा मे,<br>
श्री <strong>{{worker_name}}</strong><br>
स्थाई पता: {{permanent_address}}<br>
अस्थाई पता: {{local_address}}</p>

<h2 style="text-align:center;margin:20px 0">विषय: अस्थाई नियुक्ति</h2>

<p>महोदय,</p>

<p>दिनांक <strong>{{today_date}}</strong> के आपके <strong>{{post_applied}}</strong> पद हेतु आवेदन पत्र तथा इस सन्दर्भ मे हुए आपके साक्षात्कार के उपरान्त प्रबन्धगण द्वारा आपको उपलिखित संस्थान मे निम्नलिखित शर्तों पर नियुक्त किया जाता है:—</p>

<ol style="font-size:13px;line-height:1.8">
<li>आपकी नियुक्ति आपके आवेदन पत्र मे दी गई सूचनाओं के आधार पर की जा रही है तथा यह आवेदन पत्र आपकी सेवा शर्तों (सेवा संविदा) का एक भाग होगा। उक्त आवेदन पत्र मे किसी प्रकार की त्रुटि, अपवृत्ति, अप्रकटन अथवा अन्यथा कथन की स्थिति मे आपकी सेवाएं आपको किसी प्रकार की सूचना दिए बिना समाप्त की जा सकती है तथा उस दशा मे आपका हमारे विरुद्ध किसी प्रकार का दावा नही होगा।</li>

<li>आपको दिया गया उपरोक्त पर कार्य के अत्याई तौर पर अधिक हो जाने/कार्य का अनिवार्यता/छुट्टी रिक्त के कारण भरा जाना है जो कि दिनांक <strong>{{appointment_to}}</strong> को समाप्त हो जाएगी तथा आपकी नियुक्ति उक्त तारीख को बिना किसी कारण व पूर्व सूचना इसके बदले मे कोई अदायगी किए स्वयं समाप्त हो जाएगी।</li>

<li>जिस कार्य के लिए आपको नियुक्ति की जा रही है वो उस पद पर न तो आपका कोई हक होगा और न ही किसी स्थाई व नियमित रिक्त होने पर उक्त पद पर नियुक्त किए जाने के लिए आपको किसी प्रकार का कोई दावा होगा।</li>

<li>हमारा कार्य कुछ निश्चित आदेशों (Orders) और ठेकों (Contracts) पर आधारित है और इस स्थिति को ध्यान मे रखते हुए आपकी सेवाएं उपरोक्त अवधि को समाप्ति से पहले बिना कोई कारण बताये व यदि आपका कार्य व व्यवहार भी संतोषजनक न पाया गया तो आपकी सेवाएं समाप्त करने की स्थिति मे संस्थान द्वारा आपको केवल अर्जित वेतन का ही भुगतान किया जाएगा व आपका इसके अलावा संस्थान के विरुद्ध किसी प्रकार का कोई दावा न होगा।</li>

<li>आपकी नियुक्ति दिनांक <strong>{{appointment_from}}</strong> से वैद्य होगी।</li>

<li>(i) आप दैनिक/मासिक दर के कर्मचारी होगे और आपको कुल मिलाकर <strong>{{daily_wage}}</strong> प्रतिदिन / <strong>{{monthly_wage}}</strong> प्रतिमाह दिए जायेंगे।<br><br>
(ii) आप उत्तरी दर के कर्मचारी होगे और आपको उत्तरी दर के हिसाब मे जो कि समय-समय पर आपसे सहमति से निर्धारित किए जा सकते, राशि अदा को जाएगी।</li>

<li>आप संस्थान द्वारा नियमित कर्मचारियों को दी जा रही किसी भी लाभ व सुविधाओ के हकदार नही होगे।</li>

<li>यदि किन्ही परिस्थितियों मे अनिवार्य हुआ तो आपको किसी वैकल्पिक पद पर समान दर पर नियुक्त किया जा सकता है। संस्थान द्वारा आपको समान वेतन दर व सेवा शर्तों पर किसी ऐसे स्थान पर नियुक्त किया जा सकता है जहां संस्थान का कार्य प्रगति पर हो व जो संस्थान द्वारा आपकी नियुक्ति के पश्चात शुरु किए जा सकते है।</li>

<li>यदि आप अपने कार्य से प्रबन्धकगण की लिखित स्वीकृति के बिना निरन्तर 3 (तीन) दिन तक अनुपस्थित रहो तो यह समझते हुए कि आपने अपना कार्य खुद छोड़ दिया है, आपका नाम बिना किसी सूचना के हाज़िरी रजिस्टर/सेवा पंजिका मे से काट दिया जायेगा।</li>
</ol>

<p>यदि आपको संस्थान मे अपने रोज़गार की उपरोक्त शर्ते स्वीकार है तो आप कृपया इस पत्र, जिसके तथ्य आपने पढ़ लिए है और आपको स्पष्ट कर दिए गये है, की दूसरी प्रतिलिपि पर अपनी स्वीकृति दे।</p>

<div style="text-align:right;margin-top:30px">
<p>कृते प्रबन्ध निदेशक/प्रबन्धक/साझीदार/प्रोप्राइटर</p>
<p>________________________</p>
<p>For <strong>{{company_name}}</strong></p>
</div>

<hr style="margin:30px 0">

<p>मैने उपरोक्त पत्र के तथ्य पढ़ लिए है और मुझे इसका अर्थ भी स्पष्ट कर दिया गया है। मैं नियुक्ति स्वीकार करता हूं। इस पत्र की प्रतिलिपि मैने प्राप्त कर ली है।</p>

<div style="display:flex;justify-content:space-between;margin-top:30px">
<div>दिनांक: ___________</div>
<div style="text-align:right">
हस्ताक्षर: ________________________<br>
नाम: <strong>{{worker_name}}</strong><br>
पता: {{permanent_address}}
</div>
</div>

<p style="font-size:11px;margin-top:20px;color:#666"><strong>NOTE:</strong> In case of any difference in opinions over the interpretation of the above clause, English version shall be treated as authentic.</p>

</div>

</div>',
            ],

        ];
    }
}
