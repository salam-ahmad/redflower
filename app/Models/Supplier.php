<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    // Calculate total debt per currency
    public function totalDebt()
    {
        $debts = [];

        foreach ($this->purchases as $purchase) {
            foreach ($purchase->items as $item) {
                $currencyName = $item->currency->name;

                if (!isset($debts[$currencyName])) {
                    $debts[$currencyName] = [
                        'total' => 0,
                        'paid' => 0,
                        'remaining' => 0,
                    ];
                }

                $debts[$currencyName]['total'] += $item->total_price;
            }
        }

        // Calculate payments
        foreach ($this->payments as $payment) {
            $currencyName = $payment->currency->name;
            if (isset($debts[$currencyName])) {
                $debts[$currencyName]['paid'] += $payment->amount;
            }
        }

        // Calculate remaining
        foreach ($debts as $currency => &$debt) {
            $debt['remaining'] = $debt['total'] - $debt['paid'];
        }

        return $debts;
    }
}
