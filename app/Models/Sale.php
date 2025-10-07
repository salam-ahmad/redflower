<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sale_number',
        'customer_id',
        'sale_date',
        'payment_status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'sale_date' => 'date',
    ];

    // Auto-generate sale number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (empty($sale->sale_number)) {
                $sale->sale_number = 'SAL-' . date('Ymd') . '-' . str_pad(static::max('id') + 1, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Calculate total per currency
    public function totals()
    {
        $totals = [];

        foreach ($this->items as $item) {
            $currencyName = $item->currency->name;

            if (!isset($totals[$currencyName])) {
                $totals[$currencyName] = ['total' => 0];
            }

            $totals[$currencyName]['total'] += $item->total_price;
        }

        return $totals;
    }

    // Calculate paid amount per currency
    public function paidAmounts()
    {
        $paid = [];

        foreach ($this->payments as $payment) {
            $currencyName = $payment->currency->name;

            if (!isset($paid[$currencyName])) {
                $paid[$currencyName] = ['paid' => 0];
            }

            $paid[$currencyName]['paid'] += $payment->amount;
        }

        return $paid;
    }

    // Calculate remaining debt per currency
    public function remainingDebt()
    {
        $totals = $this->totals();
        $paid = $this->paidAmounts();
        $remaining = [];

        foreach ($totals as $currency => $data) {
            $remaining[$currency] = [
                'total' => $data['total'],
                'paid' => $paid[$currency]['paid'] ?? 0,
                'remaining' => $data['total'] - ($paid[$currency]['paid'] ?? 0),
            ];
        }

        return $remaining;
    }

    // Update payment status based on payments
    public function updatePaymentStatus()
    {
        $remainingDebt = $this->remainingDebt();
        $hasDebt = false;
        $fullyPaid = true;

        foreach ($remainingDebt as $debt) {
            if ($debt['remaining'] > 0) {
                $hasDebt = true;
                $fullyPaid = false;
            }
            if ($debt['paid'] > 0) {
                $fullyPaid = false;
            }
        }

        if (!$hasDebt && !$fullyPaid) {
            $this->payment_status = 'paid';
        } elseif ($hasDebt && !$fullyPaid) {
            $this->payment_status = 'unpaid';
        } else {
            $this->payment_status = 'partial';
        }

        $this->saveQuietly();
    }
}
