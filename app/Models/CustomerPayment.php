<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPayment extends Model
{
    use  SoftDeletes;

    protected $fillable = [
        'customer_id',
        'sale_id',
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Update sale payment status after payment created/updated/deleted
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($payment) {
            $payment->sale->updatePaymentStatus();
        });

        static::deleted(function ($payment) {
            $payment->sale->updatePaymentStatus();
        });
    }
}
