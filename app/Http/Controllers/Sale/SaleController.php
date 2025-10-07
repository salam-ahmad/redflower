<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $sales = Sale::query()
            ->with(['customer', 'items.product', 'items.currency', 'createdBy'])
            ->when($request->search, function ($query, $search) {
                $query->where('sale_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->when($request->payment_status, function ($query, $status) {
                $query->where('payment_status', $status);
            })
            ->orderByDesc('sale_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Sale/Index', [
            'sales' => $sales,
            'filters' => $request->only(['search', 'payment_status']),
        ]);
    }

    public function create(): Response
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with('currency')
            ->orderBy('name')
            ->get();
        $currencies = Currency::where('is_active', true)->get();

        return Inertia::render('Sale/Create', [
            'customers' => $customers,
            'products' => $products,
            'currencies' => $currencies,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency_id' => 'required|exists:currencies,id',
        ]);

        // Check stock availability
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            if ($product->stock_quantity < $item['quantity']) {
                return back()->withErrors([
                    'items' => "Not enough stock for {$product->name}. Available: {$product->stock_quantity}"
                ]);
            }
        }

        DB::transaction(function () use ($validated) {
            $sale = Sale::create([
                'customer_id' => $validated['customer_id'],
                'sale_date' => $validated['sale_date'],
                'notes' => $validated['notes'] ?? null,
                'payment_status' => 'unpaid',
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'currency_id' => $item['currency_id'],
                ]);
            }
        });

        return redirect()->route('sales.index')
            ->with('success', 'Sale created successfully.');
    }

    public function show(Sale $sale): Response
    {
        $sale->load([
            'customer',
            'items.product',
            'items.currency',
            'payments.currency',
            'createdBy'
        ]);

        $totals = $sale->totals();
        $paidAmounts = $sale->paidAmounts();
        $remainingDebt = $sale->remainingDebt();

        return Inertia::render('Sale/Show', [
            'sale' => $sale,
            'totals' => $totals,
            'paidAmounts' => $paidAmounts,
            'remainingDebt' => $remainingDebt,
        ]);
    }

    public function edit(Sale $sale): Response
    {
        $sale->load(['items.product', 'items.currency']);
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->with('currency')->orderBy('name')->get();
        $currencies = Currency::where('is_active', true)->get();

        return Inertia::render('Sale/Edit', [
            'sale' => $sale,
            'customers' => $customers,
            'products' => $products,
            'currencies' => $currencies,
        ]);
    }

    public function update(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:sale_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency_id' => 'required|exists:currencies,id',
        ]);

        DB::transaction(function () use ($sale, $validated) {
            $sale->update([
                'customer_id' => $validated['customer_id'],
                'sale_date' => $validated['sale_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Delete items not in the request
            $itemIds = collect($validated['items'])->pluck('id')->filter();
            $sale->items()->whereNotIn('id', $itemIds)->delete();

            // Update or create items
            foreach ($validated['items'] as $item) {
                if (isset($item['id'])) {
                    $sale->items()->where('id', $item['id'])->update([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'currency_id' => $item['currency_id'],
                    ]);
                } else {
                    $sale->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'currency_id' => $item['currency_id'],
                    ]);
                }
            }

            $sale->updatePaymentStatus();
        });

        return redirect()->route('sales.show', $sale)
            ->with('success', 'Sale updated successfully.');
    }

    public function destroy(Sale $sale)
    {
        try {
            DB::transaction(function () use ($sale) {
                $sale->items()->delete();
                $sale->delete();
            });

            return redirect()->route('sales.index')
                ->with('success', 'Sale deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('sales.index')
                ->with('error', 'Cannot delete sale. It has related records.');
        }
    }
}
