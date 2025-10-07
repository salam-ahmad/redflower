<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'barcode',
        'description',
        'buy_price',
        'sell_price',
        'currency_id',
        'stock_quantity',
        'min_stock_alert',
        'is_active',
    ];

    protected $casts = [
        'buy_price' => 'double:1',
        'sell_price' => 'double:1',
        'stock_quantity' => 'integer',
        'min_stock_alert' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    // Check if stock is low
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock_alert;
    }

    // Check if out of stock
    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }
}
