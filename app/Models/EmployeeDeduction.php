<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeeDeduction extends Pivot
{
    protected $table = 'employee_deductions';

    protected $fillable = [
        'employee_id',
        'deduction_id',
        'start_date',
        'end_date',
        'custom_amount',
        'notes',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'custom_amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function deduction()
    {
        return $this->belongsTo(Deduction::class);
    }

    public $incrementing = true;
}
