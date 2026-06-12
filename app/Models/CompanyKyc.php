<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CompanyKyc extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'company_kyc';
    protected $guarded    = ['id'];
    protected $casts      = ['documents' => 'array'];

    public function company() { return $this->belongsTo(Company::class); }

    public function isPending()   { return $this->status === 'pending'; }
    public function isSubmitted() { return $this->status === 'submitted'; }
    public function isApproved()  { return $this->status === 'approved'; }
    public function isRejected()  { return $this->status === 'rejected'; }
}
