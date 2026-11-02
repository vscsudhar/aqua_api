<?php

namespace App\Models;

use App\Traits\BelongsToCompany;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyOtherTransaction extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $table = 'daily_other_transactions';

    protected $fillable = [
        'company_id',
        'transaction_date',
        'period',
        'cane_quantity',
        'filling_amount',
        'payments_received',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'period' => 'integer',
            'cane_quantity' => 'integer',
            'filling_amount' => 'float',
            'payments_received' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DailyOtherTransaction $item) {
            if ($item->transaction_date) {
                $day = Carbon::parse($item->transaction_date)->day;
                $item->period = $day <= 15 ? 1 : 2;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
