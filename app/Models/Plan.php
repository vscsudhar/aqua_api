<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration',
        'max_users',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_users' => 'integer',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}