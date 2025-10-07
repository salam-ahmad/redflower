<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'currency_id',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'double:1',
        'total_price' => 'double:1',
    ];

    // Auto-calculate total_price
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });

        // Update product stock after creating sale item
        static::created(function ($item) {
            $product = $item->product;
            $product->stock_quantity -= $item->quantity;
            $product->save();
        });

        // Update product stock after updating sale item
        static::updated(function ($item) {
            if ($item->isDirty('quantity')) {
                $product = $item->product;
                $oldQuantity = $item->getOriginal('quantity');
                $newQuantity = $item->quantity;
                $difference = $newQuantity - $oldQuantity;

                $product->stock_quantity -= $difference;
                $product->save();
            }
        });

        // Update product stock after deleting sale item
        static::deleted(function ($item) {
            $product = $item->product;
            $product->stock_quantity += $item->quantity;
            $product->save();
        });
    }

    // Relationships
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
