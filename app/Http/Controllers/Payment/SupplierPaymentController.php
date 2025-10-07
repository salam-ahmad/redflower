<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierPaymentController extends Controller
{
    /**
     * Display a listing of supplier payments
     */
    public function index(Request $request)
    {
        $payments = SupplierPayment::with(['supplier', 'currency', 'purchase'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('supplier', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->when($request->date_from, function ($query, $date) {
                $query->whereDate('paid_at', '>=', $date);
            })
            ->when($request->date_to, function ($query, $date) {
                $query->whereDate('paid_at', '<=', $date);
            })
            ->orderBy('paid_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Add meta information
        $payments->getCollection()->transform(function ($payment) {
            return $payment;
        });

        return Inertia::render('Payment/SupplierPayment/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'date_from', 'date_to'])
        ]);
    }

    /**
     * Show the form for creating a new supplier payment
     */
    public function create()
    {
        return Inertia::render('Payment/SupplierPayment/Create', [
            'suppliers' => \App\Models\Supplier::select('id', 'name', 'due_amount')->get(),
            'currencies' => Currency::all(),
            'purchases' => \App\Models\Purchase::with('supplier')
                ->where('due_amount', '>', 0)
                ->get(),
            'paymentMethods' => ['نەقد', 'کارتی بانکی', 'چێک', 'هاوردە']
        ]);
    }

    /**
     * Show the form for creating a payment for a specific purchase
     */
    public function createForPurchase(Purchase $purchase)
    {
        $purchase->load(['supplier', 'items.currency']);

        return Inertia::render('Payment/SupplierPayment/Create', [
            'suppliers' => \App\Models\Supplier::select('id', 'name', 'due_amount')->get(),
            'currencies' => Currency::all(),
            'purchases' => \App\Models\Purchase::where('supplier_id', $purchase->supplier_id)
                ->where('due_amount', '>', 0)
                ->get(),
            'paymentMethods' => ['نەقد', 'کارتی بانکی', 'چێک', 'هاوردە'],
            'preselectedPurchase' => $purchase,
            'preselectedSupplier' => $purchase->supplier
        ]);
    }

    /**
     * Store a newly created supplier payment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_id' => 'nullable|exists:purchases,id',
            'amount' => 'required|numeric|min:0',
            'currency_id' => 'required|exists:currencies,id',
            'paid_at' => 'required|date',
            'payment_method' => 'nullable|string',
            'note' => 'nullable|string'
        ]);

        $validated['created_by_id'] = auth()->id();

        $payment = SupplierPayment::create($validated);

        // Update purchase paid amount if linked to specific purchase
        if ($payment->purchase_id) {
            $this->updatePurchaseBalance($payment->purchase_id);
        }

        // Update supplier balance
        $this->updateSupplierBalance($payment->supplier_id);

        if ($request->has('redirect_to_purchase') && $payment->purchase_id) {
            return redirect()->route('purchases.show', $payment->purchase_id)
                ->with('success', 'پارەدان بە سەرکەوتوویی زیادکرا');
        }

        return redirect()->route('supplier-payments.index')
            ->with('success', 'پارەدان بە سەرکەوتوویی زیادکرا');
    }

    /**
     * Display the specified supplier payment
     */
    public function show(SupplierPayment $supplierPayment)
    {
        $supplierPayment->load([
            'supplier',
            'currency',
            'purchase',
            'created_by'
        ]);

        return Inertia::render('Payment/SupplierPayment/Show', [
            'payment' => $supplierPayment
        ]);
    }

    /**
     * Show the form for editing the specified supplier payment
     */
    public function edit(SupplierPayment $supplierPayment)
    {
        return Inertia::render('Payment/SupplierPayment/Edit', [
            'payment' => $supplierPayment,
            'suppliers' => \App\Models\Supplier::select('id', 'name', 'due_amount')->get(),
            'currencies' => Currency::all(),
            'purchases' => \App\Models\Purchase::where('supplier_id', $supplierPayment->supplier_id)
                ->where('due_amount', '>', 0)
                ->orWhere('id', $supplierPayment->purchase_id)
                ->get(),
            'paymentMethods' => ['نەقد', 'کارتی بانکی', 'چێک', 'هاوردە']
        ]);
    }

    /**
     * Update the specified supplier payment
     */
    public function update(Request $request, SupplierPayment $supplierPayment)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_id' => 'nullable|exists:purchases,id',
            'amount' => 'required|numeric|min:0',
            'currency_id' => 'required|exists:currencies,id',
            'paid_at' => 'required|date',
            'payment_method' => 'nullable|string',
            'note' => 'nullable|string'
        ]);

        $oldPurchaseId = $supplierPayment->purchase_id;
        $oldSupplierId = $supplierPayment->supplier_id;

        $supplierPayment->update($validated);

        // Update balances for old and new purchases if changed
        if ($oldPurchaseId != $supplierPayment->purchase_id) {
            if ($oldPurchaseId) {
                $this->updatePurchaseBalance($oldPurchaseId);
            }
            if ($supplierPayment->purchase_id) {
                $this->updatePurchaseBalance($supplierPayment->purchase_id);
            }
        } else if ($supplierPayment->purchase_id) {
            $this->updatePurchaseBalance($supplierPayment->purchase_id);
        }

        // Update supplier balances
        if ($oldSupplierId != $supplierPayment->supplier_id) {
            $this->updateSupplierBalance($oldSupplierId);
            $this->updateSupplierBalance($supplierPayment->supplier_id);
        } else {
            $this->updateSupplierBalance($supplierPayment->supplier_id);
        }

        return redirect()->route('supplier-payments.show', $supplierPayment)
            ->with('success', 'پارەدان بە سەرکەوتوویی نوێکرایەوە');
    }

    /**
     * Remove the specified supplier payment
     */
    public function destroy(SupplierPayment $supplierPayment)
    {
        $purchaseId = $supplierPayment->purchase_id;
        $supplierId = $supplierPayment->supplier_id;

        $supplierPayment->delete();

        // Update balances
        if ($purchaseId) {
            $this->updatePurchaseBalance($purchaseId);
        }
        $this->updateSupplierBalance($supplierId);

        return redirect()->route('supplier-payments.index')
            ->with('success', 'پارەدان بە سەرکەوتوویی سڕایەوە');
    }

    /**
     * Get all payments for a specific supplier
     */
    public function supplierPayments(\App\Models\Supplier $supplier)
    {
        $payments = SupplierPayment::where('supplier_id', $supplier->id)
            ->with(['currency', 'purchase'])
            ->orderBy('paid_at', 'desc')
            ->paginate(20);

        return Inertia::render('Payment/SupplierPayment/Index', [
            'payments' => $payments,
            'supplier' => $supplier,
            'filters' => ['supplier_id' => $supplier->id]
        ]);
    }

    /**
     * Update purchase balance based on payments
     */
    private function updatePurchaseBalance($purchaseId)
    {
        $purchase = Purchase::findOrFail($purchaseId);
        $totalPaid = SupplierPayment::where('purchase_id', $purchaseId)->sum('amount');

        $purchase->update([
            'paid_amount' => $totalPaid,
            'due_amount' => $purchase->total - $totalPaid
        ]);
    }

    /**
     * Update supplier balance based on all their purchases and payments
     */
    private function updateSupplierBalance($supplierId)
    {
        $supplier = \App\Models\Supplier::findOrFail($supplierId);

        $totalPurchases = Purchase::where('supplier_id', $supplierId)->sum('total');
        $totalPaid = SupplierPayment::where('supplier_id', $supplierId)->sum('amount');

        $supplier->update([
            'due_amount' => $totalPurchases - $totalPaid
        ]);
    }
}
