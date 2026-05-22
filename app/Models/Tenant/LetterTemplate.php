<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['name', 'type', 'body', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function placeholders(): array
    {
        return [
            '{{employee_name}}'       => 'Full name',
            '{{employee_id}}'         => 'Employee ID (EMP-001)',
            '{{designation}}'         => 'Job title / designation / पद',
            '{{department}}'          => 'Department name',
            '{{date_of_joining}}'     => 'Date of joining / नियुक्ति तिथि',
            '{{date_of_leaving}}'     => 'Date of leaving',
            '{{date_of_birth}}'       => 'जन्म तिथि (DOB)',
            '{{age}}'                 => 'आयु (Age)',
            '{{ctc_annual}}'          => 'Annual CTC',
            '{{basic_salary}}'        => 'Monthly basic salary',
            '{{hra}}'                 => 'Monthly HRA',
            '{{special_allowance}}'   => 'Monthly special allowance',
            '{{monthly_salary}}'      => 'Total monthly salary / मासिक वेतन',
            '{{daily_wage}}'          => 'दैनिक वेतन (Monthly / 26)',
            '{{monthly_wage}}'        => 'मासिक वेतन (same as monthly_salary)',
            '{{email}}'               => 'Employee email',
            '{{phone}}'               => 'Phone / Mobile',
            '{{address}}'             => 'Full address / स्थाई पता',
            '{{pan_number}}'          => 'PAN number',
            '{{aadhaar_number}}'      => 'Aadhaar number',
            '{{pf_uan}}'              => 'PF / UAN number',
            '{{company_name}}'        => 'Company name / संस्थान',
            '{{today_date}}'          => 'Current date',
            '{{current_year}}'        => 'Current year',
            // Hindi worker-template aliases (mapped from employee data)
            '{{worker_name}}'               => 'नाम (= employee_name)',
            '{{worker_id}}'                 => 'ID (= employee_id)',
            '{{father_husband_name}}'       => 'पिता/पति (= emergency contact name)',
            '{{permanent_address}}'         => 'स्थाई पता (= address)',
            '{{local_address}}'             => 'स्थानीय पता (= address)',
            '{{mobile}}'                    => 'Mobile (= phone)',
            '{{post_applied}}'              => 'अभ्यर्थिक पद (= designation)',
            '{{post_held}}'                 => 'भारित पद (= designation)',
            '{{appointment_from}}'          => 'नियुक्ति से (= date_of_joining)',
            '{{appointment_to}}'            => 'नियुक्ति तक (fill manually)',
            '{{experience_table}}'          => 'अनुभव तालिका (auto)',
            '{{references_table}}'          => 'सन्दर्भ तालिका (auto)',
        ];
    }
}
