<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Currency;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $purchases = Purchase::query()
            ->with(['supplier', 'items.currency', 'createdBy'])
            ->when($request->search, function ($query, $search) {
                $query->where('purchase_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->when($request->payment_status, function ($query, $status) {
                $query->where('payment_status', $status);
            })
            ->orderByDesc('purchase_date')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($purchase) {
                // Aggregate totals by currency
                $totalsByCurrency = [];

                foreach ($purchase->items as $item) {
                    $currencyName = $item->currency->name;

                    if (!isset($totalsByCurrency[$currencyName])) {
                        $totalsByCurrency[$currencyName] = [
                            'currency_name' => $currencyName,
                            'total' => 0
                        ];
                    }

                    $totalsByCurrency[$currencyName]['total'] += $item->total_price;
                }

                return [
                    'id' => $purchase->id,
                    'purchase_number' => $purchase->purchase_number,
                    'supplier' => $purchase->supplier ? [
                        'id' => $purchase->supplier->id,
                        'name' => $purchase->supplier->name,
                    ] : null,
                    'purchase_date' => $purchase->purchase_date->format('Y-m-d'),
                    'payment_status' => $purchase->payment_status,
                    'totals_by_currency' => array_values($totalsByCurrency), // Convert to indexed array
                    'created_by' => $purchase->createdBy ? [
                        'id' => $purchase->createdBy->id,
                        'name' => $purchase->createdBy->name,
                    ] : null,
                    'created_at' => $purchase->created_at,
                ];
            });

        return Inertia::render('Purchase/Index', [
            'purchases' => $purchases,
            'filters' => $request->only(['search', 'payment_status']),
        ]);
    }

    public function create(Request $request): Response
    {
        // Get active suppliers
        $suppliers = Supplier::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        // Get active products with search functionality
        $products = Product::query()
            ->where('is_active', true)
            ->with(['currency:id,name'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(18) // 18 products per page (3 rows x 6 columns)
            ->withQueryString()
            ->through(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'buy_price' => $product->buy_price,
                    'stock_quantity' => $product->stock_quantity,
                    'currency_id' => $product->currency_id,
                    'currency' => $product->currency ? [
                        'id' => $product->currency->id,
                        'name' => $product->currency->name,
                    ] : null,
                ];
            });

        // Get active currencies
        $currencies = Currency::where('is_active', true)
            ->get(['id', 'name']);

        return Inertia::render('Purchase/Create', [
            'suppliers' => $suppliers,
            'products' => $products,
            'currencies' => $currencies,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'notes' => 'nullable|string',
            'payment_method' => 'required|in:cash,partial,debt',
            'items' => 'required|string', // JSON string
            'payments' => 'nullable|string', // JSON string
        ]);

        // Decode JSON strings
        $items = json_decode($validated['items'], true);
        $payments = $validated['payments'] ? json_decode($validated['payments'], true) : [];

        // Additional validation
        if (empty($items)) {
            return back()->withErrors(['items' => 'هیچ کاڵایەک هەڵنەبژێردراوە.']);
        }

        // Validate payment_method requirements
        if (in_array($validated['payment_method'], ['partial', 'debt']) && !$validated['supplier_id']) {
            return back()->withErrors(['supplier_id' => 'بۆ قەرز/نیوەقەرز، ناوی دابینکەر داواکراوە.']);
        }

        // Validate items structure
        foreach ($items as $index => $item) {
            if (!isset($item['product_id']) || !isset($item['quantity']) || !isset($item['unit_price']) || !isset($item['currency_id'])) {
                return back()->withErrors(['items' => "کاڵای ژمارە " . ($index + 1) . " داتای نادروست هەیە."]);
            }

            // Validate product exists
            $product = Product::find($item['product_id']);
            if (!$product) {
                return back()->withErrors(['items' => "کاڵای ژمارە " . ($index + 1) . " نەدۆزرایەوە."]);
            }

            // Validate currency exists
            $currency = Currency::find($item['currency_id']);
            if (!$currency) {
                return back()->withErrors(['items' => "دراوی کاڵای ژمارە " . ($index + 1) . " نادروستە."]);
            }

            // Validate quantity and price
            if ($item['quantity'] <= 0) {
                return back()->withErrors(['items' => "بڕی کاڵای ژمارە " . ($index + 1) . " دەبێت زیاتر لە سفر بێت."]);
            }

            if ($item['unit_price'] < 0) {
                return back()->withErrors(['items' => "نرخی کاڵای ژمارە " . ($index + 1) . " نادروستە."]);
            }
        }

        // Validate payments structure
        foreach ($payments as $index => $payment) {
            if (!isset($payment['currency_id']) || !isset($payment['amount'])) {
                return back()->withErrors(['payments' => "پارەدانی ژمارە " . ($index + 1) . " داتای نادروست هەیە."]);
            }

            // Validate currency exists
            $currency = Currency::find($payment['currency_id']);
            if (!$currency) {
                return back()->withErrors(['payments' => "دراوی پارەدانی ژمارە " . ($index + 1) . " نادروستە."]);
            }

            // Validate amount
            if ($payment['amount'] < 0) {
                return back()->withErrors(['payments' => "بڕی پارەدانی ژمارە " . ($index + 1) . " نادروستە."]);
            }
        }

        try {
            DB::beginTransaction();

            // Create purchase
            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                'purchase_date' => $validated['purchase_date'],
                'notes' => $validated['notes'],
                'payment_status' => 'unpaid', // Will be updated based on payments
                'created_by' => auth()->id(),
            ]);

            // Create purchase items
            foreach ($items as $item) {
                $purchase->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'currency_id' => $item['currency_id'],
                ]);
                // Note: Stock is automatically updated via PurchaseItem model boot method
            }

            // Record payments if any
            if (!empty($payments)) {
                foreach ($payments as $payment) {
                    if ($payment['amount'] > 0) {
                        SupplierPayment::create([
                            'supplier_id' => $validated['supplier_id'],
                            'purchase_id' => $purchase->id,
                            'amount' => $payment['amount'],
                            'currency_id' => $payment['currency_id'],
                            'payment_date' => $validated['purchase_date'],
                            'notes' => $payment['note'] ?? 'دفعة أولية - Initial payment',
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            // Update payment status based on payments
            $purchase->updatePaymentStatus();

            DB::commit();

            return redirect()->route('purchases.index')
                ->with('success', 'کڕینەکە بەسەرکەوتویی تۆمارکرا.');

        } catch (\Exception $e) {
            DB::rollBack();

            // Log the error for debugging
            \Log::error('Purchase creation failed: ' . $e->getMessage());

            return back()->withErrors(['error' => 'هەڵەیەک ڕوویدا لە کاتی پاشەکەوتکردن. تکایە دووبارە هەوڵبدەوە.']);
        }
    }

    public function show(Purchase $purchase)
    {
        // Load all necessary relationships
        $purchase->load([
            'supplier',
            'items.product',
            'items.currency',
            'payments' => function ($query) {
                $query->with('currency')->orderBy('paid_at', 'desc');
            }
        ]);

        return Inertia::render('Purchase/Show', [
            'purchase' => $purchase
        ]);
    }

    public function edit(Purchase $purchase): Response
    {
        $purchase->load(['items.product', 'items.currency']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->with('currency')->orderBy('name')->get();
        $currencies = Currency::where('is_active', true)->get();

        return Inertia::render('Purchase/Edit', [
            'purchase' => $purchase,
            'suppliers' => $suppliers,
            'products' => $products,
            'currencies' => $currencies,
        ]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:purchase_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency_id' => 'required|exists:currencies,id',
        ]);

        DB::transaction(function () use ($purchase, $validated) {
            $purchase->update([
                'supplier_id' => $validated['supplier_id'],
                'purchase_date' => $validated['purchase_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Delete items not in the request
            $itemIds = collect($validated['items'])->pluck('id')->filter();
            $purchase->items()->whereNotIn('id', $itemIds)->delete();

            // Update or create items
            foreach ($validated['items'] as $item) {
                if (isset($item['id'])) {
                    $purchase->items()->where('id', $item['id'])->update([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'currency_id' => $item['currency_id'],
                    ]);
                } else {
                    $purchase->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'currency_id' => $item['currency_id'],
                    ]);
                }
            }

            $purchase->updatePaymentStatus();
        });

        return redirect()->route('purchases.show', $purchase)
            ->with('success', 'Purchase updated successfully.');
    }

    public function destroy(Purchase $purchase)
    {
        try {
            DB::transaction(function () use ($purchase) {
                // Delete associated payments first
                $purchase->payments()->delete();

                // Delete purchase items (stock will be automatically adjusted via model events)
                $purchase->items()->delete();

                // Delete the purchase
                $purchase->delete();
            });

            return redirect()->route('purchases.index')
                ->with('success', 'کڕینەکە بەسەرکەوتویی سڕایەوە.');
        } catch (\Exception $e) {
            \Log::error('Purchase deletion failed: ' . $e->getMessage());

            return redirect()->route('purchases.index')
                ->with('error', 'ناتوانرێت کڕینەکە بسڕێتەوە. تۆمارەکانی پەیوەندیدار هەن.');
        }
    }

    /**
     * Get all purchases for a specific supplier
     */
    public function supplierPurchases(\App\Models\Supplier $supplier)
    {
        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->with(['items', 'payments'])
            ->orderBy('date', 'desc')
            ->paginate(20);

        return Inertia::render('Purchase/Index', [
            'purchases' => $purchases,
            'supplier' => $supplier,
            'filters' => ['supplier_id' => $supplier->id]
        ]);
    }
}
