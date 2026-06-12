# Expense Upload Feature - File Placement Guide

## Files to place:

### 1. Migration
`migration/2026_06_03_000001_add_upload_fields_to_expenses.php`
→ `database/migrations/tenant/2026_06_03_000001_add_upload_fields_to_expenses.php`

### 2. Service (NEW file)
`service/ExpenseExtractorService.php`
→ `app/Services/ExpenseExtractorService.php`

### 3. Controllers
`controller/ExpenseUploadController.php` (NEW)
→ `app/Http/Controllers/Tenant/ExpenseUploadController.php`

`controller/ExpensesController.php` (REPLACE existing)
→ `app/Http/Controllers/Tenant/ExpensesController.php`

### 4. Model (REPLACE existing)
`model/Expense.php`
→ `app/Models/Tenant/Expense.php`

### 5. Vue Page (REPLACE existing)
`vue/Index.vue`
→ `resources/js/Pages/Tenant/Expenses/Index.vue`

### 6. Routes (ADD to routes/tenant.php)
Add these 2 lines inside the tenant route group (after existing expense routes):

```php
Route::post('/expenses/extract', [\App\Http\Controllers\Tenant\ExpenseUploadController::class, 'extract'])->name('expenses.extract');
Route::post('/expenses/upload',  [\App\Http\Controllers\Tenant\ExpenseUploadController::class, 'store'])->name('expenses.upload.store');
```

### 7. Run migration
```bash
php artisan tenants:migrate --path=database/migrations/tenant/2026_06_03_000001_add_upload_fields_to_expenses.php
```

## Flow:
1. User clicks "Upload" → drag-drop or browse for image/PDF/CSV
2. File uploads to server → Bunny CDN storage + AI extraction via DO Serverless Inference
3. AI returns structured data (vendor, amount, date, category, line items)
4. If vendor is clear → auto-fills label, goes to preview
5. If unclear (handwritten) → prompts user for title, then preview
6. User reviews/edits extracted data → confirms → expense saved
7. For CSV: shows all rows as checkboxes, user selects which to import
