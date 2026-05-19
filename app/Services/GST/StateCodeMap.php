<?php

declare(strict_types=1);

namespace App\Services\GST;

/**
 * Maps the first 2 digits of a GSTIN to state/UT name and code.
 * Source: CBIC GSTIN format specification.
 */
class StateCodeMap
{
    public const STATES = [
        '01' => ['name' => 'Jammu & Kashmir', 'code' => 'JK'],
        '02' => ['name' => 'Himachal Pradesh', 'code' => 'HP'],
        '03' => ['name' => 'Punjab', 'code' => 'PB'],
        '04' => ['name' => 'Chandigarh', 'code' => 'CH'],
        '05' => ['name' => 'Uttarakhand', 'code' => 'UK'],
        '06' => ['name' => 'Haryana', 'code' => 'HR'],
        '07' => ['name' => 'Delhi', 'code' => 'DL'],
        '08' => ['name' => 'Rajasthan', 'code' => 'RJ'],
        '09' => ['name' => 'Uttar Pradesh', 'code' => 'UP'],
        '10' => ['name' => 'Bihar', 'code' => 'BR'],
        '11' => ['name' => 'Sikkim', 'code' => 'SK'],
        '12' => ['name' => 'Arunachal Pradesh', 'code' => 'AR'],
        '13' => ['name' => 'Nagaland', 'code' => 'NL'],
        '14' => ['name' => 'Manipur', 'code' => 'MN'],
        '15' => ['name' => 'Mizoram', 'code' => 'MZ'],
        '16' => ['name' => 'Tripura', 'code' => 'TR'],
        '17' => ['name' => 'Meghalaya', 'code' => 'ML'],
        '18' => ['name' => 'Assam', 'code' => 'AS'],
        '19' => ['name' => 'West Bengal', 'code' => 'WB'],
        '20' => ['name' => 'Jharkhand', 'code' => 'JH'],
        '21' => ['name' => 'Odisha', 'code' => 'OD'],
        '22' => ['name' => 'Chhattisgarh', 'code' => 'CG'],
        '23' => ['name' => 'Madhya Pradesh', 'code' => 'MP'],
        '24' => ['name' => 'Gujarat', 'code' => 'GJ'],
        '26' => ['name' => 'Dadra & Nagar Haveli and Daman & Diu', 'code' => 'DD'],
        '27' => ['name' => 'Maharashtra', 'code' => 'MH'],
        '28' => ['name' => 'Andhra Pradesh (old)', 'code' => 'AP'],
        '29' => ['name' => 'Karnataka', 'code' => 'KA'],
        '30' => ['name' => 'Goa', 'code' => 'GA'],
        '31' => ['name' => 'Lakshadweep', 'code' => 'LD'],
        '32' => ['name' => 'Kerala', 'code' => 'KL'],
        '33' => ['name' => 'Tamil Nadu', 'code' => 'TN'],
        '34' => ['name' => 'Puducherry', 'code' => 'PY'],
        '35' => ['name' => 'Andaman & Nicobar', 'code' => 'AN'],
        '36' => ['name' => 'Telangana', 'code' => 'TS'],
        '37' => ['name' => 'Andhra Pradesh', 'code' => 'AD'],
        '38' => ['name' => 'Ladakh', 'code' => 'LA'],
        '97' => ['name' => 'Other Territory', 'code' => 'OT'],
    ];

    /**
     * Shopify province codes (IN-XX) → GSTIN state code (2-digit).
     * Shopify uses ISO 3166-2:IN codes like "IN-MH", "IN-KA", etc.
     */
    public const SHOPIFY_PROVINCE_TO_STATE_CODE = [
        'JK' => '01', 'HP' => '02', 'PB' => '03', 'CH' => '04', 'UK' => '05',
        'HR' => '06', 'DL' => '07', 'RJ' => '08', 'UP' => '09', 'BR' => '10',
        'SK' => '11', 'AR' => '12', 'NL' => '13', 'MN' => '14', 'MZ' => '15',
        'TR' => '16', 'ML' => '17', 'AS' => '18', 'WB' => '19', 'JH' => '20',
        'OD' => '21', 'OR' => '21', 'CG' => '22', 'CT' => '22', 'MP' => '23',
        'GJ' => '24', 'GA' => '30', 'DD' => '26', 'DN' => '26',
        'MH' => '27', 'AP' => '37', 'KA' => '29', 'LD' => '31',
        'KL' => '32', 'TN' => '33', 'PY' => '34', 'AN' => '35',
        'TS' => '36', 'LA' => '38',
    ];

    /** Extract state code (first 2 digits) from a 15-character GSTIN. */
    public static function stateCodeFromGstin(string $gstin): ?string
    {
        $code = substr(trim($gstin), 0, 2);
        return isset(self::STATES[$code]) ? $code : null;
    }

    /** Get state name from GSTIN state code. */
    public static function stateName(string $stateCode): string
    {
        return self::STATES[$stateCode]['name'] ?? 'Unknown';
    }

    /** Convert Shopify province code (e.g. "MH", "KA") to GSTIN state code. */
    public static function shopifyProvinceToStateCode(string $provinceCode): ?string
    {
        // Shopify sometimes sends "IN-MH", sometimes just "MH"
        $code = str_replace('IN-', '', strtoupper(trim($provinceCode)));
        return self::SHOPIFY_PROVINCE_TO_STATE_CODE[$code] ?? null;
    }
}
