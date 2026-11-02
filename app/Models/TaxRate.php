<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'rate',
        'tax_type',
        'applicability',
        'effective_from',
        'effective_to',
        'is_active',
        'is_default',
        'description',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'effective_from' => 'date:Y-m-d',
        'effective_to' => 'date:Y-m-d',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
