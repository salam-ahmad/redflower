<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{

    protected $fillable = [
        'purchase_id',
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

        // Update product stock after creating purchase item
        static::created(function ($item) {
            $product = $item->product;
            $product->stock_quantity += $item->quantity;
            $product->save();
        });

        // Update product stock after updating purchase item
        static::updated(function ($item) {
            if ($item->isDirty('quantity')) {
                $product = $item->product;
                $oldQuantity = $item->getOriginal('quantity');
                $newQuantity = $item->quantity;
                $difference = $newQuantity - $oldQuantity;

                $product->stock_quantity += $difference;
                $product->save();
            }
        });

        // Update product stock after deleting purchase item
        static::deleted(function ($item) {
            $product = $item->product;
            $product->stock_quantity -= $item->quantity;
            $product->save();
        });
    }

    // Relationships
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
