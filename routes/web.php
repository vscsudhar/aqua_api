<?php

use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invoices/{id}', [InvoiceController::class, 'showHtml'])->name('invoices.show-direct');
Route::get('/invoices/{id}/html', [InvoiceController::class, 'showHtml'])->name('invoices.show-html');
Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.download-pdf');
Route::get('/invoices/{id}/print', [InvoiceController::class, 'printPdf'])->name('invoices.print-pdf');
