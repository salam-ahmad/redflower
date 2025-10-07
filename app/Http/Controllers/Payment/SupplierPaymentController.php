<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\SupplierPayment;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = SupplierPayment::query()
            ->with(['supplier', 'purchase', 'currency', 'createdBy'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('supplier', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('purchase', function ($q) use ($search) {
                        $q->where('purchase_number', 'like', "%{$search}%");
                    });
            })
            ->when($request->supplier_id, function ($query, $supplierId) {
                $query->where('supplier_id', $supplierId);
            })
            ->orderByDesc('payment_date')
            ->paginate(15)
            ->withQueryString();

        $suppliers = Supplier::orderBy('name')->get();

        return Inertia::render('Payment/SupplierPayment/Index', [
            'payments' => $payments,
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'supplier_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $currencies = Currency::where('is_active', true)->get();

        $purchases = [];
        if ($request->supplier_id) {
            $purchases = Purchase::where('supplier_id', $request->supplier_id)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->with(['items.currency'])
                ->orderByDesc('purchase_date')
                ->get()
                ->map(function ($purchase) {
                    return [
                        'id' => $purchase->id,
                        'purchase_number' => $purchase->purchase_number,
                        'purchase_date' => $purchase->purchase_date,
                        'remaining_debt' => $purchase->remainingDebt(),
                    ];
                });
        }

        return Inertia::render('Payment/SupplierPayment/Create', [
            'suppliers' => $suppliers,
            'currencies' => $currencies,
            'purchases' => $purchases,
            'selectedSupplierId' => $request->supplier_id,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_id' => 'required|exists:purchases,id',
            'amount' => 'required|numeric|min:0.1',
            'currency_id' => 'required|exists:currencies,id',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        // Verify purchase belongs to supplier
        $purchase = Purchase::findOrFail($validated['purchase_id']);
        if ($purchase->supplier_id != $validated['supplier_id']) {
            return back()->withErrors(['purchase_id' => 'This purchase does not belong to the selected supplier.']);
        }

        SupplierPayment::create([
            'supplier_id' => $validated['supplier_id'],
            'purchase_id' => $validated['purchase_id'],
            'amount' => $validated['amount'],
            'currency_id' => $validated['currency_id'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('supplier-payments.index')
            ->with('success', 'Payment recorded successfully.');
    }

    public function show(SupplierPayment $supplierPayment): Response
    {
        $supplierPayment->load(['supplier', 'purchase.items.currency', 'currency', 'createdBy']);

        return Inertia::render('Payment/SupplierPayment/Show', [
            'payment' => $supplierPayment,
        ]);
    }

    public function edit(SupplierPayment $supplierPayment): Response
    {
        $supplierPayment->load(['supplier', 'purchase']);
        $currencies = Currency::where('is_active', true)->get();

        return Inertia::render('Payment/SupplierPayment/Edit', [
            'payment' => $supplierPayment,
            'currencies' => $currencies,
        ]);
    }

    public function update(Request $request, SupplierPayment $supplierPayment)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.1',
            'currency_id' => 'required|exists:currencies,id',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $supplierPayment->update($validated);

        return redirect()->route('supplier-payments.index')
            ->with('success', 'Payment updated successfully.');
    }

    public function destroy(SupplierPayment $supplierPayment)
    {
        $supplierPayment->delete();

        return redirect()->route('supplier-payments.index')
            ->with('success', 'Payment deleted successfully.');
    }

    // Get unpaid purchases for a supplier (AJAX endpoint)
    public function getUnpaidPurchases(Request $request)
    {
        $supplierId = $request->supplier_id;

        $purchases = Purchase::where('supplier_id', $supplierId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->with(['items.currency'])
            ->orderByDesc('purchase_date')
            ->get()
            ->map(function ($purchase) {
                return [
                    'id' => $purchase->id,
                    'purchase_number' => $purchase->purchase_number,
                    'purchase_date' => $purchase->purchase_date->format('Y-m-d'),
                    'remaining_debt' => $purchase->remainingDebt(),
                ];
            });

        return response()->json($purchases);
    }
}
