<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeave extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'document_path',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
