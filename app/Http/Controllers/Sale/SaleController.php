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
    /**
     * Display a listing of sales
     */
    public function index(Request $request)
    {
        $sales = Sale::with(['customer', 'items'])
            ->when($request->search, function ($query, $search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->when($request->date_from, function ($query, $date) {
                $query->whereDate('sale_date', '>=', $date);
            })
            ->when($request->date_to, function ($query, $date) {
                $query->whereDate('sale_date', '<=', $date);
            })
            ->when($request->customer_id, function ($query, $customerId) {
                $query->where('customer_id', $customerId);
            })
            ->orderBy('sale_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['search', 'date_from', 'date_to', 'customer_id'])
        ]);
    }

    /**
     * Show the form for creating a new sale
     */
    public function create(Request $request)
    {
        $customers = Customer::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
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
                    'sell_price' => $product->sell_price,
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
        return Inertia::render('Sales/Create', [
            'customers' => $customers,
            'products' => $products,
            'currencies' => $currencies,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created sale
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_date' => 'required|date',
            'notes' => 'nullable|string',
            'payment_status' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency_id' => 'required|exists:currencies,id',
            'payments' => 'nullable|array',
        ]);
        $validated['created_by'] = auth()->id();
        // Calculate total
        $total = collect($validated['items'])->sum(function ($item) {
            return $item['quantity'] * $item['unit_price'];
        });

        $validated['total'] = $total;
        $validated['paid_amount'] = 0;
        $validated['due_amount'] = $total;

        $sale = Sale::create($validated);
        $sale->items()->createMany($validated['items']);

        // Update customer balance
        $this->updateCustomerBalance($sale->customer_id);

        return redirect()->route('sales.show', $sale)
            ->with('success', 'فرۆشتن بە سەرکەوتوویی زیادکرا');
    }

    /**
     * Display the specified sale with payments
     */
    public function show(Sale $sale)
    {
        // Load all necessary relationships
        $sale->load([
            'customer',
            'items.product',
            'items.currency',
            'payments' => function ($query) {
                $query->with('currency')->orderBy('paid_at', 'desc');
            }
        ]);

        return Inertia::render('Sales/Show', [
            'sale' => $sale
        ]);
    }

    /**
     * Show the form for editing the specified sale
     */
    public function edit(Sale $sale)
    {
        $sale->load(['items.product', 'items.currency']);

        return Inertia::render('Sales/Edit', [
            'sale' => $sale,
            'customers' => \App\Models\Customer::select('id', 'name', 'phone')->get(),
            'products' => \App\Models\Product::with('unit')->get(),
            'currencies' => \App\Models\Currency::all()
        ]);
    }

    /**
     * Update the specified sale
     */
    public function update(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency_id' => 'required|exists:currencies,id',
            'items.*.note' => 'nullable|string'
        ]);

        $oldCustomerId = $sale->customer_id;

        // Calculate new total
        $total = collect($validated['items'])->sum(function ($item) {
            return $item['quantity'] * $item['unit_price'];
        });

        $validated['total'] = $total;
        $validated['due_amount'] = $total - $sale->paid_amount;

        $sale->update($validated);

        // Delete old items and create new ones
        $sale->items()->delete();
        $sale->items()->createMany($validated['items']);

        // Update customer balances
        if ($oldCustomerId != $sale->customer_id) {
            $this->updateCustomerBalance($oldCustomerId);
            $this->updateCustomerBalance($sale->customer_id);
        } else {
            $this->updateCustomerBalance($sale->customer_id);
        }

        return redirect()->route('sales.show', $sale)
            ->with('success', 'فرۆشتن بە سەرکەوتوویی نوێکرایەوە');
    }

    /**
     * Remove the specified sale
     */
    public function destroy(Sale $sale)
    {
        $customerId = $sale->customer_id;

        $sale->items()->delete();
        $sale->delete();

        $this->updateCustomerBalance($customerId);

        return redirect()->route('sales.index')
            ->with('success', 'فرۆشتن بە سەرکەوتوویی سڕایەوە');
    }

    /**
     * Get all sales for a specific customer
     */
    public function customerSales(\App\Models\Customer $customer)
    {
        $sales = Sale::where('customer_id', $customer->id)
            ->with(['items', 'payments'])
            ->orderBy('date', 'desc')
            ->paginate(20);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'customer' => $customer,
            'filters' => ['customer_id' => $customer->id]
        ]);
    }

    /**
     * Update customer balance based on all their sales and payments
     */
    private function updateCustomerBalance($customerId)
    {
        $customer = \App\Models\Customer::findOrFail($customerId);

        $totalSales = Sale::where('customer_id', $customerId)->sum('total');
        $totalPaid = \App\Models\CustomerPayment::where('customer_id', $customerId)->sum('amount');

        $customer->update([
            'due_amount' => $totalSales - $totalPaid
        ]);
    }
}

