<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Currency\CurrencyController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Payment\CustomerPaymentController;
use App\Http\Controllers\Payment\SupplierPaymentController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Purchase\PurchaseController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\Sale\SaleController;
use App\Http\Controllers\Setting\SettingController;
use App\Http\Controllers\Supplier\SupplierController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Welcome\WelcomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return Inertia::render('Home/Index');
    })->name('home');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Currency Management
    Route::resource('currencies', CurrencyController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
    // Supplier Management
    Route::resource('suppliers', SupplierController::class);
    // Customer Management
    Route::resource('customers', CustomerController::class);
    // Product Management
    Route::resource('products', ProductController::class);
    // Purchase Management
    Route::resource('purchases', PurchaseController::class);
    // Sale Management
    Route::resource('sales', SaleController::class);
    // Supplier Payments
    Route::resource('supplier-payments', SupplierPaymentController::class);
    Route::get('/api/unpaid-purchases', [SupplierPaymentController::class, 'getUnpaidPurchases'])->name('api.unpaid-purchases');
    // Customer Payments
    Route::resource('customer-payments', CustomerPaymentController::class);
    Route::get('/api/unpaid-sales', [CustomerPaymentController::class, 'getUnpaidSales'])->name('api.unpaid-sales');
    Route::get('/edit_password', [AuthController::class, 'editPassword'])->name('edit_password');
    Route::put('/updatePassword', [AuthController::class, 'updatePassword'])->name('updatePassword');

//    Route::get('/register', [AuthController::class, 'create'])->name('register');
//    Route::post('/register', [AuthController::class, 'store']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/welcome', [WelcomeController::class, 'index'])->name('welcome');
    Route::put('assign_permissions_to_roles', [RoleController::class, 'editRole']);
    Route::get('/role/{role}', [RoleController::class, 'getPermissionsByRoleId'])->name('role.permissions');
    Route::get('/users/index', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::resource('setting', SettingController::class)->names('settings');

    // ==========================================
// Customer Payment Routes
// ==========================================
//    Route::resource('customer-payments', CustomerPaymentController::class);

// Create payment for specific sale
//    Route::get('sales/{sale}/payments/create', [CustomerPaymentController::class, 'createForSale'])->name('sales.payments.create');

// ==========================================
// Supplier Payment Routes
// ==========================================
//    Route::resource('supplier-payments', SupplierPaymentController::class);

// Create payment for specific purchase
//    Route::get('purchases/{purchase}/payments/create', [SupplierPaymentController::class, 'createForPurchase'])->name('purchases.payments.create');

// ==========================================
// Purchase & Sale Routes (existing)
// ==========================================
//    Route::resource('purchases', PurchaseController::class);
//    Route::resource('sales', SaleController::class);

// View customer's sales
//    Route::get('customers/{customer}/sales', [SaleController::class, 'indexByCustomer'])->name('customers.sales');

// View supplier's purchases
//    Route::get('suppliers/{supplier}/purchases', [PurchaseController::class, 'indexBySupplier'])->name('suppliers.purchases');

});
Route::middleware('guest')->group(function () {
    Route::inertia('/login', 'Auth/Login')->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'create'])->name('register');
    Route::post('/register', [AuthController::class, 'store']);
});
