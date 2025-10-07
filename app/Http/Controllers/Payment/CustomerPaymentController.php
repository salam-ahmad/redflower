<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\CustomerPayment;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerPaymentController extends Controller
{
    /**
     * Display a listing of customer payments
     */
    public function index(Request $request)
    {
        $payments = CustomerPayment::with(['customer', 'currency', 'sale'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('customer', function ($q) use ($search) {
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

        return Inertia::render('Payment/CustomerPayment/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'date_from', 'date_to'])
        ]);
    }

    /**
     * Show the form for creating a new customer payment
     */
    public function create()
    {
        return Inertia::render('Payment/CustomerPayment/Create', [
            'customers' => \App\Models\Customer::select('id', 'name', 'due_amount')->get(),
            'currencies' => Currency::all(),
            'sales' => Sale::with('customer')
                ->where('due_amount', '>', 0)
                ->get(),
            'paymentMethods' => ['نەقد', 'کارتی بانکی', 'چێک', 'هاوردە']
        ]);
    }

    /**
     * Show the form for creating a payment for a specific sale
     */
    public function createForSale(Sale $sale)
    {
        $sale->load(['customer', 'items.currency']);

        return Inertia::render('Payment/CustomerPayment/Create', [
            'customers' => \App\Models\Customer::select('id', 'name', 'due_amount')->get(),
            'currencies' => Currency::all(),
            'sales' => Sale::where('customer_id', $sale->customer_id)
                ->where('due_amount', '>', 0)
                ->get(),
            'paymentMethods' => ['نەقد', 'کارتی بانکی', 'چێک', 'هاوردە'],
            'preselectedSale' => $sale,
            'preselectedCustomer' => $sale->customer
        ]);
    }

    /**
     * Store a newly created customer payment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_id' => 'nullable|exists:sales,id',
            'amount' => 'required|numeric|min:0',
            'currency_id' => 'required|exists:currencies,id',
            'paid_at' => 'required|date',
            'payment_method' => 'nullable|string',
            'note' => 'nullable|string'
        ]);

        $validated['created_by_id'] = auth()->id();

        $payment = CustomerPayment::create($validated);

        // Update sale paid amount if linked to specific sale
        if ($payment->sale_id) {
            $this->updateSaleBalance($payment->sale_id);
        }

        // Update customer balance
        $this->updateCustomerBalance($payment->customer_id);

        if ($request->has('redirect_to_sale') && $payment->sale_id) {
            return redirect()->route('sales.show', $payment->sale_id)
                ->with('success', 'پارەوەرگرتن بە سەرکەوتوویی زیادکرا');
        }

        return redirect()->route('customer-payments.index')
            ->with('success', 'پارەوەرگرتن بە سەرکەوتوویی زیادکرا');
    }

    /**
     * Display the specified customer payment
     */
    public function show(CustomerPayment $customerPayment)
    {
        $customerPayment->load([
            'customer',
            'currency',
            'sale',
            'created_by'
        ]);

        return Inertia::render('Payment/CustomerPayment/Show', [
            'payment' => $customerPayment
        ]);
    }

    /**
     * Show the form for editing the specified customer payment
     */
    public function edit(CustomerPayment $customerPayment)
    {
        return Inertia::render('Payment/CustomerPayment/Edit', [
            'payment' => $customerPayment,
            'customers' => \App\Models\Customer::select('id', 'name', 'due_amount')->get(),
            'currencies' => Currency::all(),
            'sales' => Sale::where('customer_id', $customerPayment->customer_id)
                ->where('due_amount', '>', 0)
                ->orWhere('id', $customerPayment->sale_id)
                ->get(),
            'paymentMethods' => ['نەقد', 'کارتی بانکی', 'چێک', 'هاوردە']
        ]);
    }

    /**
     * Update the specified customer payment
     */
    public function update(Request $request, CustomerPayment $customerPayment)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_id' => 'nullable|exists:sales,id',
            'amount' => 'required|numeric|min:0',
            'currency_id' => 'required|exists:currencies,id',
            'paid_at' => 'required|date',
            'payment_method' => 'nullable|string',
            'note' => 'nullable|string'
        ]);

        $oldSaleId = $customerPayment->sale_id;
        $oldCustomerId = $customerPayment->customer_id;

        $customerPayment->update($validated);

        // Update balances for old and new sales if changed
        if ($oldSaleId != $customerPayment->sale_id) {
            if ($oldSaleId) {
                $this->updateSaleBalance($oldSaleId);
            }
            if ($customerPayment->sale_id) {
                $this->updateSaleBalance($customerPayment->sale_id);
            }
        } else if ($customerPayment->sale_id) {
            $this->updateSaleBalance($customerPayment->sale_id);
        }

        // Update customer balances
        if ($oldCustomerId != $customerPayment->customer_id) {
            $this->updateCustomerBalance($oldCustomerId);
            $this->updateCustomerBalance($customerPayment->customer_id);
        } else {
            $this->updateCustomerBalance($customerPayment->customer_id);
        }

        return redirect()->route('customer-payments.show', $customerPayment)
            ->with('success', 'پارەوەرگرتن بە سەرکەوتوویی نوێکرایەوە');
    }

    /**
     * Remove the specified customer payment
     */
    public function destroy(CustomerPayment $customerPayment)
    {
        $saleId = $customerPayment->sale_id;
        $customerId = $customerPayment->customer_id;

        $customerPayment->delete();

        // Update balances
        if ($saleId) {
            $this->updateSaleBalance($saleId);
        }
        $this->updateCustomerBalance($customerId);

        return redirect()->route('customer-payments.index')
            ->with('success', 'پارەوەرگرتن بە سەرکەوتوویی سڕایەوە');
    }

    /**
     * Get all payments for a specific customer
     */
    public function customerPayments(\App\Models\Customer $customer)
    {
        $payments = CustomerPayment::where('customer_id', $customer->id)
            ->with(['currency', 'sale'])
            ->orderBy('paid_at', 'desc')
            ->paginate(20);

        return Inertia::render('Payment/CustomerPayment/Index', [
            'payments' => $payments,
            'customer' => $customer,
            'filters' => ['customer_id' => $customer->id]
        ]);
    }

    /**
     * Update sale balance based on payments
     */
    private function updateSaleBalance($saleId)
    {
        $sale = Sale::findOrFail($saleId);
        $totalPaid = CustomerPayment::where('sale_id', $saleId)->sum('amount');

        $sale->update([
            'paid_amount' => $totalPaid,
            'due_amount' => $sale->total - $totalPaid
        ]);
    }

    /**
     * Update customer balance based on all their sales and payments
     */
    private function updateCustomerBalance($customerId)
    {
        $customer = \App\Models\Customer::findOrFail($customerId);

        $totalSales = Sale::where('customer_id', $customerId)->sum('total');
        $totalPaid = CustomerPayment::where('customer_id', $customerId)->sum('amount');

        $customer->update([
            'due_amount' => $totalSales - $totalPaid
        ]);
    }
}
