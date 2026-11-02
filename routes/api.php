<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanySettingController;
use App\Http\Controllers\Api\CompanyUserController;
use App\Http\Controllers\Api\DailyCollectionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DealerController;
use App\Http\Controllers\Api\DealerLedgerReportController;
use App\Http\Controllers\Api\DealerPriceController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TaxRateController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Marina Aqua Multi-Tenant Dealer Management System
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ==========================================
    // Super Admin Routes (AQUA_USER Main Admin)
    // ==========================================
    Route::prefix('super-admin')->group(function () {
        Route::post('/login', [SuperAdminController::class, 'login'])->middleware('throttle:120,1');

        Route::middleware(['auth:sanctum', SuperAdminMiddleware::class])->group(function () {
            Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);

            // Companies CRUD
            Route::get('/companies', [SuperAdminController::class, 'companiesIndex']);
            Route::post('/companies', [SuperAdminController::class, 'companiesStore']);
            Route::get('/companies/{id}', [SuperAdminController::class, 'companiesShow']);
            Route::put('/companies/{id}', [SuperAdminController::class, 'companiesUpdate']);
            Route::patch('/companies/{id}/toggle-status', [SuperAdminController::class, 'companiesToggleStatus']);
            Route::delete('/companies/{id}', [SuperAdminController::class, 'companiesDestroy']);

            // Company Users Management (Plan Limit Enforced)
            Route::get('/companies/{id}/users', [SuperAdminController::class, 'companyUsersIndex']);
            Route::post('/companies/{id}/users', [SuperAdminController::class, 'companyUsersStore']);
            Route::put('/companies/{id}/users/{userId}', [SuperAdminController::class, 'companyUsersUpdate']);
            Route::patch('/companies/{id}/users/{userId}/toggle-status', [SuperAdminController::class, 'companyUsersToggleStatus']);
            Route::delete('/companies/{id}/users/{userId}', [SuperAdminController::class, 'companyUsersDestroy']);

            // Plans CRUD
            Route::get('/plans', [SuperAdminController::class, 'plansIndex']);
            Route::post('/plans', [SuperAdminController::class, 'plansStore']);
            Route::put('/plans/{id}', [SuperAdminController::class, 'plansUpdate']);
            Route::patch('/plans/{id}/toggle-status', [SuperAdminController::class, 'plansToggleStatus']);
            Route::delete('/plans/{id}', [SuperAdminController::class, 'plansDestroy']);
        });
    });

    // ==========================================
    // Public Authentication & Assets
    // ==========================================
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:120,1');
    });

    // Public Company Logo Endpoints (CORS-enabled for Web Image rendering)
    Route::get('/public/company-logo/{companyId}', [CompanySettingController::class, 'streamPublicLogo']);
    Route::get('/public/logo-file/{filename}', [CompanySettingController::class, 'streamLogoFile']);

    // ==========================================
    // Authenticated Tenant Business Routes
    // ==========================================
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureCompanyPlanActiveMiddleware::class])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/change-password', [AuthController::class, 'updatePassword']);
        });

        // Company Staff User Management Module (Requirement 4)
        Route::prefix('company')->group(function () {
            Route::get('/users', [CompanyUserController::class, 'index']);
            Route::post('/users', [CompanyUserController::class, 'store']);
            Route::get('/users/{id}', [CompanyUserController::class, 'show']);
            Route::put('/users/{id}', [CompanyUserController::class, 'update']);
            Route::patch('/users/{id}/toggle-status', [CompanyUserController::class, 'toggleStatus']);
            Route::delete('/users/{id}', [CompanyUserController::class, 'destroy']);
        });

        // Dashboard Module
        Route::prefix('dashboard')->group(function () {
            Route::get('/summary', [DashboardController::class, 'summary']);
            Route::get('/charts', [DashboardController::class, 'charts']);
        });

        // Company Settings & Tax Management Module
        Route::get('company-settings', [CompanySettingController::class, 'show']);
        Route::put('company-settings', [CompanySettingController::class, 'update']);
        Route::post('company-settings/logo', [CompanySettingController::class, 'uploadLogo']);
        Route::delete('company-settings/logo', [CompanySettingController::class, 'deleteLogo']);
        Route::get('company-settings/logo-stream', [CompanySettingController::class, 'streamLogo']);

        Route::patch('tax-rates/{id}/toggle-status', [TaxRateController::class, 'toggleStatus']);
        Route::apiResource('tax-rates', TaxRateController::class);

        // Billing & Invoices
        Route::prefix('invoices')->group(function () {
            Route::post('/{id}/finalize', [InvoiceController::class, 'finalize']);
            Route::post('/{id}/cancel', [InvoiceController::class, 'cancel']);
            Route::post('/{id}/credit-note', [InvoiceController::class, 'creditNote']);
            Route::post('/{id}/payments', [InvoiceController::class, 'recordPayment']);
            Route::get('/{id}/pdf', [InvoiceController::class, 'downloadPdf']);
            Route::get('/{id}/print', [InvoiceController::class, 'printPdf']);
        });
        Route::apiResource('invoices', InvoiceController::class);

        // Purchase & Inventory Management Module
        Route::apiResource('suppliers', SupplierController::class);
        Route::post('purchases/{id}/reverse', [PurchaseController::class, 'reverse']);
        Route::apiResource('purchases', PurchaseController::class);

        Route::prefix('inventory')->group(function () {
            Route::get('/summary', [InventoryController::class, 'summary']);
            Route::get('/movements', [InventoryController::class, 'movements']);
            Route::get('/adjustments', [InventoryController::class, 'adjustmentsIndex']);
            Route::post('/adjustments', [InventoryController::class, 'adjustmentsStore']);
        });

        // Product Master Module
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('units', UnitController::class);
        Route::patch('products/{id}/toggle-status', [ProductController::class, 'toggleStatus']);
        Route::apiResource('products', ProductController::class);

        // Dealer Module & Dealer-Specific Pricing
        Route::patch('dealers/{id}/toggle-status', [DealerController::class, 'toggleStatus']);
        Route::get('dealers/{id}/ledger', [DealerController::class, 'ledger']);
        Route::get('dealers/{id}/prices', [DealerPriceController::class, 'index']);
        Route::post('dealers/{id}/prices', [DealerPriceController::class, 'store']);
        Route::delete('dealers/{id}/prices/{priceId}', [DealerPriceController::class, 'destroy']);
        Route::get('dealers/{id}/prices/resolve', [DealerPriceController::class, 'resolvePrice']);
        Route::apiResource('dealers', DealerController::class);

        // Daily Collection & Supply Module
        Route::prefix('collections')->group(function () {
            Route::get('/daily-summary', [DailyCollectionController::class, 'summary']);
            Route::get('/dealers', [DailyCollectionController::class, 'dealers']);
        });

        Route::prefix('supplies')->group(function () {
            Route::get('/', [DailyCollectionController::class, 'suppliesIndex']);
            Route::post('/', [DailyCollectionController::class, 'suppliesStore']);
            Route::get('/{id}', [DailyCollectionController::class, 'suppliesShow']);
            Route::put('/{id}', [DailyCollectionController::class, 'suppliesUpdate']);
        });

        Route::prefix('payments')->group(function () {
            Route::get('/', [DailyCollectionController::class, 'paymentsIndex']);
            Route::post('/', [DailyCollectionController::class, 'paymentsStore']);
        });

        // Reports: Monthly Dealer Ledger Module
        Route::prefix('reports/dealer-ledger')->group(function () {
            Route::get('/', [DealerLedgerReportController::class, 'index']);
            Route::get('/others', [DealerLedgerReportController::class, 'getOthers']);
            Route::post('/others', [DealerLedgerReportController::class, 'storeOthers']);
            Route::get('/export-pdf', [DealerLedgerReportController::class, 'exportPdf']);
            Route::get('/export-excel', [DealerLedgerReportController::class, 'exportExcel']);
            Route::get('/export-csv', [DealerLedgerReportController::class, 'exportCsv']);
        });

        Route::get('/ping', function () {
            return response()->json([
                'status' => 'success',
                'message' => 'Marina Aqua API is operational.',
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    });
});