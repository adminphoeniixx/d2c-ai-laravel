<?php
/*
 * ADD these routes inside your tenant route group, near the existing expenses routes.
 * 
 * Existing routes should look like:
 *   Route::get('/expenses', [ExpensesController::class, 'index'])->name('expenses');
 *   Route::post('/expenses', [ExpensesController::class, 'store'])->name('expenses.store');
 *   Route::delete('/expenses/{id}', [ExpensesController::class, 'destroy'])->name('expenses.destroy');
 *
 * ADD these lines after them:
 */

// Expense Upload (AI extraction)
Route::post('/expenses/extract', [\App\Http\Controllers\Tenant\ExpenseUploadController::class, 'extract'])->name('expenses.extract');
Route::post('/expenses/upload',  [\App\Http\Controllers\Tenant\ExpenseUploadController::class, 'store'])->name('expenses.upload.store');
