<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // Get currency list
        $currencies = Currency::where('is_active', true)->get();

        // Calculate total debts we owe to suppliers (per currency)
        $supplierDebts = $this->calculateSupplierDebts();

        // Calculate total debts customers owe us (per currency)
        $customerDebts = $this->calculateCustomerDebts();

        // Get low stock products
        $lowStockProducts = Product::whereRaw('stock_quantity <= min_stock_alert')
            ->with('currency')
            ->orderBy('stock_quantity')
            ->limit(10)
            ->get();

        // Get out of stock products
        $outOfStockProducts = Product::where('stock_quantity', '<=', 0)
            ->with('currency')
            ->count();

        // Recent transactions
        $recentPurchases = Purchase::with(['supplier', 'items.currency'])
            ->orderByDesc('purchase_date')
            ->limit(5)
            ->get();

        $recentSales = Sale::with(['customer', 'items.currency'])
            ->orderByDesc('sale_date')
            ->limit(5)
            ->get();

        // Payment status statistics
        $purchaseStats = [
            'total' => Purchase::count(),
            'paid' => Purchase::where('payment_status', 'paid')->count(),
            'partial' => Purchase::where('payment_status', 'partial')->count(),
            'unpaid' => Purchase::where('payment_status', 'unpaid')->count(),
        ];

        $saleStats = [
            'total' => Sale::count(),
            'paid' => Sale::where('payment_status', 'paid')->count(),
            'partial' => Sale::where('payment_status', 'partial')->count(),
            'unpaid' => Sale::where('payment_status', 'unpaid')->count(),
        ];

        // Calculate profit/loss per currency
        $profitLoss = $this->calculateProfitLoss();

        return Inertia::render('Dashboard/Index', [
            'currencies' => $currencies,
            'supplierDebts' => $supplierDebts,
            'customerDebts' => $customerDebts,
            'lowStockProducts' => $lowStockProducts,
            'outOfStockProducts' => $outOfStockProducts,
            'recentPurchases' => $recentPurchases,
            'recentSales' => $recentSales,
            'purchaseStats' => $purchaseStats,
            'saleStats' => $saleStats,
            'profitLoss' => $profitLoss,
            'totalSuppliers' => Supplier::count(),
            'totalCustomers' => Customer::count(),
            'totalProducts' => Product::count(),
        ]);
    }

    private function calculateSupplierDebts(): array
    {
        $debts = [];

        $suppliers = Supplier::with(['purchases.items.currency', 'payments.currency'])->get();

        foreach ($suppliers as $supplier) {
            $supplierDebts = $supplier->totalDebt();

            foreach ($supplierDebts as $currencyCode => $debt) {
                if ($debt['remaining'] > 0) {
                    if (!isset($debts[$currencyCode])) {
                        $debts[$currencyCode] = [
                            'total' => 0,
                            'symbol' => $debt['symbol'],
                        ];
                    }
                    $debts[$currencyCode]['total'] += $debt['remaining'];
                }
            }
        }

        return $debts;
    }

    private function calculateCustomerDebts(): array
    {
        $debts = [];

        $customers = Customer::with(['sales.items.currency', 'payments.currency'])->get();

        foreach ($customers as $customer) {
            $customerDebts = $customer->totalDebt();

            foreach ($customerDebts as $currencyCode => $debt) {
                if ($debt['remaining'] > 0) {
                    if (!isset($debts[$currencyCode])) {
                        $debts[$currencyCode] = [
                            'total' => 0,
                            'symbol' => $debt['symbol'],
                        ];
                    }
                    $debts[$currencyCode]['total'] += $debt['remaining'];
                }
            }
        }

        return $debts;
    }

    private function calculateProfitLoss(): array
    {
        $profitLoss = [];

        // Get all completed sales (paid)
        $sales = Sale::where('payment_status', 'paid')
            ->with(['items.product', 'items.currency'])
            ->get();

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $currencyCode = $item->currency->code;

                if (!isset($profitLoss[$currencyCode])) {
                    $profitLoss[$currencyCode] = [
                        'revenue' => 0,
                        'cost' => 0,
                        'profit' => 0,
                        'symbol' => $item->currency->symbol,
                    ];
                }

                // Revenue from sale
                $profitLoss[$currencyCode]['revenue'] += $item->total_price;

                // Cost (buy price * quantity)
                $profitLoss[$currencyCode]['cost'] += ($item->product->buy_price * $item->quantity);
            }
        }

        // Calculate profit
        foreach ($profitLoss as $currency => &$data) {
            $data['profit'] = $data['revenue'] - $data['cost'];
        }

        return $profitLoss;
    }
}
