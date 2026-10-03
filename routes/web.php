<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemMasterController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LocationTypeController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\CatalogExportController;
use App\Http\Controllers\StockRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/scan', [ScanController::class, 'index'])->middleware(['permission:items.view|stock.adjust|loans.handover|requests.fulfill|checks.view|checks.perform', 'throttle:scan'])->name('scan.index');
    Route::post('/scan/stock', [ScanController::class, 'moveStock'])->middleware(['permission:stock.adjust', 'throttle:operations'])->name('scan.stock');

    Route::get('/locations', [LocationController::class, 'index'])->middleware('permission:locations.manage')->name('locations.index');
    Route::post('/location-types', [LocationTypeController::class, 'store'])->middleware(['permission:locations.manage', 'throttle:operations'])->name('location-types.store');
    Route::put('/location-types/{locationType}', [LocationTypeController::class, 'update'])->middleware(['permission:locations.manage', 'throttle:operations'])->name('location-types.update');
    Route::post('/location-types/{locationType}/archive', [LocationTypeController::class, 'archive'])->middleware(['permission:locations.manage', 'throttle:operations'])->name('location-types.archive');
    Route::post('/location-types/{locationType}/restore', [LocationTypeController::class, 'restore'])->middleware(['permission:locations.manage', 'throttle:operations'])->name('location-types.restore');
    Route::post('/locations', [LocationController::class, 'store'])->middleware(['permission:locations.manage', 'throttle:operations'])->name('locations.store');
    Route::put('/locations/{location}', [LocationController::class, 'update'])->middleware(['permission:locations.manage', 'throttle:operations'])->name('locations.update');
    Route::post('/locations/{location}/archive', [LocationController::class, 'archive'])->middleware(['permission:locations.manage', 'throttle:operations'])->name('locations.archive');
    Route::post('/locations/{location}/restore', [LocationController::class, 'restore'])->middleware(['permission:locations.manage', 'throttle:operations'])->name('locations.restore');

    Route::get('/inspections', [InspectionController::class, 'index'])->middleware('permission:checks.view|checks.perform')->name('inspections.index');
    Route::get('/inspections/locations/{location}', [InspectionController::class, 'create'])->middleware('permission:checks.view|checks.perform')->name('inspections.create');
    Route::post('/inspections/locations/{location}', [InspectionController::class, 'store'])->middleware(['permission:checks.perform', 'throttle:operations'])->name('inspections.store');
    Route::get('/inspections/locations/{location}/label', [InspectionController::class, 'label'])->middleware('permission:checks.view|checks.perform|locations.manage')->name('inspections.label');
    Route::get('/inspections/{inspection}', [InspectionController::class, 'show'])->middleware('permission:checks.view|checks.perform')->name('inspections.show');

    Route::get('/item-masters', [ItemMasterController::class, 'index'])->middleware('permission:items.view|items.manage')->name('item-masters.index');
    Route::get('/item-masters/create', [ItemMasterController::class, 'create'])->middleware('permission:items.manage')->name('item-masters.create');
    Route::post('/item-masters', [ItemMasterController::class, 'store'])->middleware(['permission:items.manage', 'throttle:operations'])->name('item-masters.store');
    Route::get('/item-masters/{itemMaster}/edit', [ItemMasterController::class, 'edit'])->middleware('permission:items.manage')->name('item-masters.edit');
    Route::put('/item-masters/{itemMaster}', [ItemMasterController::class, 'update'])->middleware(['permission:items.manage', 'throttle:operations'])->name('item-masters.update');
    Route::delete('/item-masters/{itemMaster}', [ItemMasterController::class, 'destroy'])->middleware(['permission:items.delete-master', 'throttle:operations'])->name('item-masters.destroy');
    Route::post('/item-masters/{itemMaster}/restore', [ItemMasterController::class, 'restore'])->middleware(['permission:items.delete-master', 'throttle:operations'])->name('item-masters.restore');

    Route::get('/items', [ItemController::class, 'index'])->middleware('permission:items.view')->name('items.index');
    Route::get('/items/export/location', CatalogExportController::class)->middleware(['permission:items.view', 'permission:stock.export', 'throttle:operations'])->name('items.export-location');
    Route::get('/items/create', [ItemController::class, 'create'])->middleware('permission:items.manage')->name('items.create');
    Route::post('/items', [ItemController::class, 'store'])->middleware(['permission:items.manage', 'throttle:operations'])->name('items.store');
    Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->middleware('permission:items.manage')->name('items.edit');
    Route::put('/items/{item}', [ItemController::class, 'update'])->middleware(['permission:items.manage', 'throttle:operations'])->name('items.update');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->middleware(['permission:items.manage', 'throttle:operations'])->name('items.destroy');
    Route::post('/items/{item}/restore', [ItemController::class, 'restore'])->middleware(['permission:items.manage', 'throttle:operations'])->name('items.restore');
    Route::get('/items/{item}/movements', [ItemController::class, 'movements'])->middleware('permission:items.view')->name('items.movements');
    Route::get('/items/{item}/label', [ItemController::class, 'label'])->middleware('permission:items.view')->name('items.label');
    Route::post('/items/{item}/adjust', [ItemController::class, 'adjust'])->middleware(['permission:stock.adjust', 'throttle:operations'])->name('items.adjust');

    Route::get('/loans', [LoanController::class, 'index'])->middleware('permission:loans.create|loans.view-all')->name('loans.index');
    Route::get('/loans/create', [LoanController::class, 'create'])->middleware('permission:loans.create')->name('loans.create');
    Route::post('/loans', [LoanController::class, 'store'])->middleware(['permission:loans.create', 'throttle:operations'])->name('loans.store');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->middleware('permission:loans.create|loans.view-all')->name('loans.show');
    Route::post('/loans/{loan}/{action}', [LoanController::class, 'transition'])->middleware('throttle:operations')->name('loans.transition');

    Route::get('/requests', [StockRequestController::class, 'index'])->middleware('permission:requests.create|requests.view-all')->name('requests.index');
    Route::get('/requests/create', [StockRequestController::class, 'create'])->middleware('permission:requests.create')->name('requests.create');
    Route::post('/requests', [StockRequestController::class, 'store'])->middleware(['permission:requests.create', 'throttle:operations'])->name('requests.store');
    Route::get('/requests/{stockRequest}', [StockRequestController::class, 'show'])->middleware('permission:requests.create|requests.view-all')->name('requests.show');
    Route::post('/requests/{stockRequest}/{action}', [StockRequestController::class, 'transition'])->middleware('throttle:operations')->name('requests.transition');

    Route::get('/access', [AccessController::class, 'index'])->middleware('permission:access.manage')->name('access.index');
    Route::post('/access/roles', [AccessController::class, 'saveRole'])->middleware(['permission:access.manage', 'throttle:operations'])->name('access.roles.store');
    Route::put('/access/roles/{role}', [AccessController::class, 'saveRole'])->middleware(['permission:access.manage', 'throttle:operations'])->name('access.roles.update');
    Route::post('/access/users', [AccessController::class, 'saveUser'])->middleware(['permission:access.manage', 'throttle:operations'])->name('access.users.store');
    Route::put('/access/users/{user}', [AccessController::class, 'saveUser'])->middleware(['permission:access.manage', 'throttle:operations'])->name('access.users.update');
});
