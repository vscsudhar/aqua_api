<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'amount',
        'expense_date',
        'expense_time',
        'payment_mode',
        'paid_to',
        'description',
        'attachment_path',
        'attachment_original_name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'category_id' => 'integer',
        'amount' => 'decimal:2',
        'expense_date' => 'date:Y-m-d',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected $hidden = [
        'attachment_path',
    ];

    protected $appends = [
        'has_attachment',
    ];

    public function getHasAttachmentAttribute(): bool
    {
        return ! empty($this->attachment_path);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id')->withTrashed();
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
