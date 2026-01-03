<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeePerception extends Pivot
{
    protected $table = 'employee_perceptions';

    protected $fillable = [
        'employee_id',
        'perception_id',
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

    public function perception()
    {
        return $this->belongsTo(Perception::class);
    }

    public $incrementing = true;
}
