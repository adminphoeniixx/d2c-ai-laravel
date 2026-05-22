<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['employee_id','name','type','file_name','file_url','file_size','mime_type','notes'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
