<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicParty extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'party_name',
        'contact_number',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PublicSale::class, 'party_id');
    }

    public function hasTransactions(): bool
    {
        return $this->sales()->exists();
    }
}
