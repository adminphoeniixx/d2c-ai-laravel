<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class PgInvoice extends Model
{
    protected $guarded = ['id'];
    protected $casts   = [
        'gross_volume'  => 'float',
        'total_charges' => 'float',
        'gst_amount'    => 'float',
        'net_settled'   => 'float',
        'period_start'  => 'date',
        'period_end'    => 'date',
    ];
}
