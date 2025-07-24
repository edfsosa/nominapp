<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'city',
        'coordinates',
    ];

    protected $casts = [
        'coordinates' => 'array',  // Laravel convertirá automáticamente JSON ↔ array
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
