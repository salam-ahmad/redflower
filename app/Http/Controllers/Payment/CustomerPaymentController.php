<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = CustomerPayment::query()
            ->with(['customer', 'sale', 'currency', 'createdBy'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('customer', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('sale', function ($q) use ($search) {
                        $q->where('sale_number', 'like', "%{$search}%");
                    });
            })
            ->when($request->customer_id, function ($query, $customerId) {
                $query->where('customer_id', $customerId);
            })
            ->orderByDesc('payment_date')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::orderBy('name')->get();

        return Inertia::render('Payment/CustomerPayment/Index', [
            'payments' => $payments,
            'customers' => $customers,
            'filters' => $request->only(['search', 'customer_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $currencies = Currency::where('is_active', true)->get();

        $sales = [];
        if ($request->customer_id) {
            $sales = Sale::where('customer_id', $request->customer_id)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->with(['items.currency'])
                ->orderByDesc('sale_date')
                ->get()
                ->map(function ($sale) {
                    return [
                        'id' => $sale->id,
                        'sale_number' => $sale->sale_number,
                        'sale_date' => $sale->sale_date,
                        'remaining_debt' => $sale->remainingDebt(),
                    ];
                });
        }

        return Inertia::render('Payment/CustomerPayment/Create', [
            'customers' => $customers,
            'currencies' => $currencies,
            'sales' => $sales,
            'selectedCustomerId' => $request->customer_id,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_id' => 'required|exists:sales,id',
            'amount' => 'required|numeric|min:0.1',
            'currency_id' => 'required|exists:currencies,id',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        // Verify sale belongs to customer
        $sale = Sale::findOrFail($validated['sale_id']);
        if ($sale->customer_id != $validated['customer_id']) {
            return back()->withErrors(['sale_id' => 'This sale does not belong to the selected customer.']);
        }

        CustomerPayment::create([
            'customer_id' => $validated['customer_id'],
            'sale_id' => $validated['sale_id'],
            'amount' => $validated['amount'],
            'currency_id' => $validated['currency_id'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('customer-payments.index')
            ->with('success', 'Payment recorded successfully.');
    }

    public function show(CustomerPayment $customerPayment): Response
    {
        $customerPayment->load(['customer', 'sale.items.currency', 'currency', 'createdBy']);

        return Inertia::render('Payment/CustomerPayment/Show', [
            'payment' => $customerPayment,
        ]);
    }

    public function edit(CustomerPayment $customerPayment): Response
    {
        $customerPayment->load(['customer', 'sale']);
        $currencies = Currency::where('is_active', true)->get();

        return Inertia::render('Payment/CustomerPayment/Edit', [
            'payment' => $customerPayment,
            'currencies' => $currencies,
        ]);
    }

    public function update(Request $request, CustomerPayment $customerPayment)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.1',
            'currency_id' => 'required|exists:currencies,id',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $customerPayment->update($validated);

        return redirect()->route('customer-payments.index')
            ->with('success', 'Payment updated successfully.');
    }

    public function destroy(CustomerPayment $customerPayment)
    {
        $customerPayment->delete();

        return redirect()->route('customer-payments.index')
            ->with('success', 'Payment deleted successfully.');
    }

    // Get unpaid sales for a customer (AJAX endpoint)
    public function getUnpaidSales(Request $request)
    {
        $customerId = $request->customer_id;

        $sales = Sale::where('customer_id', $customerId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->with(['items.currency'])
            ->orderByDesc('sale_date')
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'sale_date' => $sale->sale_date->format('Y-m-d'),
                    'remaining_debt' => $sale->remainingDebt(),
                ];
            });

        return response()->json($sales);
    }
}
