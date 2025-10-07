<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPayment extends Model
{
    use  SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'purchase_id',
        'amount',
        'currency_id',
        'payment_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'double:1',
        'payment_date' => 'date',
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Update purchase payment status after payment created/updated/deleted
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($payment) {
            $payment->purchase->updatePaymentStatus();
        });

        static::deleted(function ($payment) {
            $payment->purchase->updatePaymentStatus();
        });
    }
}
