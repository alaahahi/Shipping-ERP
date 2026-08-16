<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyDirectChargeController;
use App\Http\Controllers\CompanyWalletController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DubaiAccountController;
use App\Http\Controllers\IranCarController;
use App\Http\Controllers\IranCarImportController;
use App\Http\Controllers\IranCarPaymentController;
use App\Http\Controllers\IranCarPoolPaymentController;
use App\Http\Controllers\IranCarPrintController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LandTripCarStatusController;
use App\Http\Controllers\LandTripController;
use App\Http\Controllers\LandTripImportController;
use App\Http\Controllers\LandTripImportLogController;
use App\Http\Controllers\LandTripLocationLogController;
use App\Http\Controllers\MoneyVoucherController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShipController;
use App\Http\Controllers\ShipExpenseController;
use App\Http\Controllers\ShipExpensePrintController;
use App\Http\Controllers\ShipOwnershipController;
use App\Http\Controllers\ShipPartnerContributionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\VoyageCarController;
use App\Http\Controllers\VoyageCompanyController;
use App\Http\Controllers\VoyageController;
use App\Http\Controllers\VoyageExpenseController;
use App\Http\Controllers\VoyageSettlementController;
use App\Http\Controllers\WhatsappNotificationController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/preferences', [UserPreferenceController::class, 'update'])->name('preferences.update');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');

    Route::resource('users', UserController::class)->except(['show']);

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/countries', [CountryController::class, 'store'])->name('settings.countries.store');
    Route::put('/settings/countries/{country}', [CountryController::class, 'update'])->name('settings.countries.update');
    Route::delete('/settings/countries/{country}', [CountryController::class, 'destroy'])->name('settings.countries.destroy');
    Route::post('/settings/land-car-statuses', [LandTripCarStatusController::class, 'store'])->name('settings.land-car-statuses.store');
    Route::put('/settings/land-car-statuses/{land_trip_car_status}', [LandTripCarStatusController::class, 'update'])->name('settings.land-car-statuses.update');
    Route::delete('/settings/land-car-statuses/{land_trip_car_status}', [LandTripCarStatusController::class, 'destroy'])->name('settings.land-car-statuses.destroy');
    Route::post('/settings/system/migrate', [SettingController::class, 'migrate'])->name('settings.system.migrate');
    Route::get('/settings/system/logs/download', [SettingController::class, 'downloadLogs'])->name('settings.system.logs.download');
    Route::post('/settings/system/logs/clear', [SettingController::class, 'clearLogs'])->name('settings.system.logs.clear');
    Route::get('/settings/system/database-insights', [SettingController::class, 'databaseInsights'])->name('settings.system.db.insights');
    Route::post('/settings/system/database-vacuum', [SettingController::class, 'vacuumDatabase'])->name('settings.system.db.vacuum');

    Route::get('/whatsapp-notifications', [WhatsappNotificationController::class, 'index'])
        ->name('whatsapp-notifications.index');
    Route::post('/whatsapp-notifications/{notification}/retry', [WhatsappNotificationController::class, 'retry'])
        ->name('whatsapp-notifications.retry');
    Route::post('/whatsapp-settings', [WhatsappNotificationController::class, 'updateSettings'])
        ->name('whatsapp-settings.update');

    Route::get('/accounts/feed', [AccountController::class, 'feed'])->name('accounts.feed');
    Route::get('/accounts/{account}/export/excel', [AccountController::class, 'exportExcel'])->name('accounts.export.excel');
    Route::get('/accounts/{account}/export/pdf', [AccountController::class, 'exportPdf'])->name('accounts.export.pdf');
    Route::resource('accounts', AccountController::class);
    Route::post('/accounts/{account}/dashboard-flag', [AccountController::class, 'toggleDashboard'])
        ->name('accounts.dashboard.toggle');
    Route::post('/accounts/{account}/movements', [AccountController::class, 'storeMovement'])
        ->name('accounts.movements.store');
    Route::post('/accounts/{account}/journals/{journal}', [AccountController::class, 'updateMovement'])
        ->name('accounts.journals.update');
    Route::post('/accounts/{account}/journals/{journal}/void', [AccountController::class, 'voidMovement'])
        ->name('accounts.journals.void');

    Route::resource('ships', ShipController::class);
    Route::resource('companies', CompanyController::class);
    Route::post('/companies/{company}/direct-charges', [CompanyDirectChargeController::class, 'store'])
        ->name('companies.direct-charges.store');

    Route::get('/land-trips/companies/{company}', [LandTripController::class, 'showCompany'])
        ->name('land-trips.companies.show');
    Route::get('/land-trips/companies/{company}/cars', [LandTripController::class, 'companyCars'])
        ->name('land-trips.companies.cars');
    Route::get('/land-trips/companies/{company}/car-check', [LandTripController::class, 'companyCarCheck'])
        ->name('land-trips.companies.car-check');
    Route::post('/land-trips/companies/{company}/cars', [LandTripController::class, 'syncCompanyCars'])
        ->name('land-trips.companies.cars.sync');
    Route::post('/land-trips/companies/{company}/cars/location', [LandTripController::class, 'bulkUpdateCompanyCarStatus'])
        ->name('land-trips.companies.cars.location');
    Route::delete('/land-trips/companies/{company}/cars', [LandTripController::class, 'destroyCompanyCars'])
        ->name('land-trips.companies.cars.destroy');
    Route::patch('/land-trips/companies/{company}/cars/{car}/price', [LandTripController::class, 'updateCompanyCarPrice'])
        ->name('land-trips.companies.cars.price');
    Route::patch('/land-trips/companies/{company}/cars/{car}', [LandTripController::class, 'updateCompanyCarDetails'])
        ->name('land-trips.companies.cars.update');
    Route::get('/land-trips/companies/{company}/location-logs', [LandTripLocationLogController::class, 'index'])
        ->name('land-trips.companies.location-logs');
    Route::post('/land-trips/companies/{company}/location-logs/undo', [LandTripLocationLogController::class, 'undo'])
        ->name('land-trips.companies.location-logs.undo');
    Route::get('/land-trips/companies/{company}/import-logs', [LandTripImportLogController::class, 'index'])
        ->name('land-trips.companies.import-logs');
    Route::post('/land-trips/companies/{company}/import-logs/undo', [LandTripImportLogController::class, 'undo'])
        ->name('land-trips.companies.import-logs.undo');
    Route::put('/land-trips/companies/{company}/manifest', [LandTripController::class, 'updateCompanyManifest'])
        ->name('land-trips.companies.manifest.update');
    Route::get('/land-trips/companies/{company}/import', [LandTripController::class, 'companyImport'])
        ->name('land-trips.companies.import');
    Route::get('/land-trips/companies/{company}/export', [LandTripController::class, 'exportCompany'])
        ->name('land-trips.companies.export');
    Route::post('/land-trips/companies/{company}/wallet', [CompanyWalletController::class, 'store'])
        ->name('land-trips.companies.wallet.store');
    Route::delete('/land-trips/companies/{company}/wallet/{entry}', [CompanyWalletController::class, 'destroy'])
        ->name('land-trips.companies.wallet.destroy');
    Route::get('/land-trips/companies/{company}/wallet/{entry}/print', [CompanyWalletController::class, 'print'])
        ->name('land-trips.companies.wallet.print');
    Route::resource('land-trips', LandTripController::class);
    Route::get('/land-trips/{land_trip}/import', [LandTripImportController::class, 'create'])->name('land-trips.import');
    Route::post('/land-trips/{land_trip}/import/preview', [LandTripImportController::class, 'preview'])->name('land-trips.import.preview');
    Route::post('/land-trips/{land_trip}/import/reset', [LandTripImportController::class, 'reset'])->name('land-trips.import.reset');
    Route::post('/land-trips/{land_trip}/import/confirm', [LandTripImportController::class, 'confirm'])->name('land-trips.import.confirm');
    Route::post('/land-trips/{land_trip}/cars', [LandTripController::class, 'syncCars'])
        ->name('land-trips.cars.sync');
    Route::post('/land-trips/{land_trip}/transition', [LandTripController::class, 'transition'])
        ->name('land-trips.transition');
    Route::post('/land-trips/{land_trip}/post', [LandTripController::class, 'post'])
        ->name('land-trips.post');

    Route::get('/iran-cars/export', [IranCarController::class, 'export'])->name('iran-cars.export');
    Route::get('/iran-cars/print', [IranCarPrintController::class, 'list'])->name('iran-cars.print');
    Route::get('/iran-cars/import', [IranCarImportController::class, 'create'])->name('iran-cars.import');
    Route::post('/iran-cars/import/preview', [IranCarImportController::class, 'preview'])->name('iran-cars.import.preview');
    Route::post('/iran-cars/import/confirm', [IranCarImportController::class, 'confirm'])->name('iran-cars.import.confirm');
    Route::post('/iran-cars/pool-payments', [IranCarPoolPaymentController::class, 'store'])
        ->name('iran-cars.pool-payments.store');
    Route::delete('/iran-cars/pool-payments/{iran_car_pool_payment}', [IranCarPoolPaymentController::class, 'destroy'])
        ->name('iran-cars.pool-payments.destroy');
    Route::resource('iran-cars', IranCarController::class);
    Route::get('/iran-cars/{iran_car}/print', [IranCarPrintController::class, 'car'])->name('iran-cars.car.print');
    Route::post('/iran-cars/{iran_car}/sell', [IranCarController::class, 'sell'])->name('iran-cars.sell');
    Route::post('/iran-cars/{iran_car}/payments', [IranCarPaymentController::class, 'store'])
        ->name('iran-cars.payments.store');
    Route::delete('/iran-cars/{iran_car}/payments/{iran_car_payment}', [IranCarPaymentController::class, 'destroy'])
        ->name('iran-cars.payments.destroy');

    Route::resource('dubai-accounts', DubaiAccountController::class);
    Route::post('/dubai-accounts/{dubai_account}/entries', [DubaiAccountController::class, 'storeEntry'])
        ->name('dubai-accounts.entries.store');
    Route::delete('/dubai-accounts/{dubai_account}/entries/{entry}', [DubaiAccountController::class, 'destroyEntry'])
        ->name('dubai-accounts.entries.destroy');
    Route::post('/dubai-accounts/{dubai_account}/import-soa', [DubaiAccountController::class, 'importSoa'])
        ->name('dubai-accounts.import-soa');
    Route::post('/dubai-accounts/{dubai_account}/entries/{entry}/cars/preview', [DubaiAccountController::class, 'previewCars'])
        ->name('dubai-accounts.cars.preview');
    Route::post('/dubai-accounts/{dubai_account}/entries/{entry}/cars/confirm', [DubaiAccountController::class, 'confirmCars'])
        ->name('dubai-accounts.cars.confirm');

    Route::post('/ships/{ship}/ownerships', [ShipOwnershipController::class, 'store'])
        ->name('ships.ownerships.store');
    Route::put('/ships/{ship}/ownerships/{ownership}', [ShipOwnershipController::class, 'update'])
        ->name('ships.ownerships.update');
    Route::delete('/ships/{ship}/ownerships/{ownership}', [ShipOwnershipController::class, 'destroy'])
        ->name('ships.ownerships.destroy');
    Route::get('/ships/{ship}/expenses/print', ShipExpensePrintController::class)
        ->name('ships.expenses.print');
    Route::post('/ships/{ship}/expenses', [ShipExpenseController::class, 'store'])
        ->name('ships.expenses.store');
    Route::post('/ships/{ship}/expenses/bulk', [ShipExpenseController::class, 'bulkStore'])
        ->name('ships.expenses.bulk');
    Route::post('/ships/{ship}/expenses/import', [ShipExpenseController::class, 'import'])
        ->name('ships.expenses.import');
    Route::put('/ships/{ship}/expenses/{expense}', [ShipExpenseController::class, 'update'])
        ->name('ships.expenses.update');
    Route::delete('/ships/{ship}/expenses/{expense}', [ShipExpenseController::class, 'destroy'])
        ->name('ships.expenses.destroy');
    Route::post('/ships/{ship}/expenses/{expense}/post', [ShipExpenseController::class, 'post'])
        ->name('ships.expenses.post');
    Route::post('/ships/{ship}/contributions', [ShipPartnerContributionController::class, 'store'])
        ->name('ships.contributions.store');
    Route::post('/ships/{ship}/contributions/bulk', [ShipPartnerContributionController::class, 'bulkStore'])
        ->name('ships.contributions.bulk');
    Route::post('/ships/{ship}/contributions/import', [ShipPartnerContributionController::class, 'import'])
        ->name('ships.contributions.import');
    Route::put('/ships/{ship}/contributions/{contribution}', [ShipPartnerContributionController::class, 'update'])
        ->name('ships.contributions.update');
    Route::delete('/ships/{ship}/contributions/{contribution}', [ShipPartnerContributionController::class, 'destroy'])
        ->name('ships.contributions.destroy');
    Route::post('/ships/{ship}/contributions/{contribution}/post', [ShipPartnerContributionController::class, 'post'])
        ->name('ships.contributions.post');
    Route::resource('voyages', VoyageController::class);
    Route::get('/voyages/{voyage}/tracking', [VoyageController::class, 'tracking'])
        ->name('voyages.tracking');
    Route::post('/voyages/{voyage}/waypoints', [VoyageController::class, 'storeWaypoint'])
        ->name('voyages.waypoints.store');
    Route::delete('/voyages/{voyage}/waypoints/{waypoint}', [VoyageController::class, 'destroyWaypoint'])
        ->name('voyages.waypoints.destroy');
    Route::post('/voyages/{voyage}/transition', [VoyageController::class, 'transition'])
        ->name('voyages.transition');
    Route::post('/voyages/{voyage}/settlements/post-revenue', [VoyageSettlementController::class, 'postRevenue'])
        ->name('voyages.settlements.post-revenue');
    Route::post('/voyages/{voyage}/settlements/post-commission', [VoyageSettlementController::class, 'postCommission'])
        ->name('voyages.settlements.post-commission');
    Route::post('/voyages/{voyage}/companies', [VoyageCompanyController::class, 'store'])
        ->name('voyages.companies.store');
    Route::put('/voyages/{voyage}/companies/{company}', [VoyageCompanyController::class, 'update'])
        ->name('voyages.companies.update');
    Route::delete('/voyages/{voyage}/companies/{company}', [VoyageCompanyController::class, 'destroy'])
        ->name('voyages.companies.destroy');

    Route::post('/voyages/{voyage}/cars', [VoyageCarController::class, 'store'])
        ->name('voyages.cars.store');
    Route::put('/voyages/{voyage}/cars/{car}', [VoyageCarController::class, 'update'])
        ->name('voyages.cars.update');
    Route::delete('/voyages/{voyage}/cars/{car}', [VoyageCarController::class, 'destroy'])
        ->name('voyages.cars.destroy');
    Route::post('/voyages/{voyage}/cars/import', [VoyageCarController::class, 'import'])
        ->name('voyages.cars.import');
    Route::post('/voyages/{voyage}/cars/import-preview', [VoyageCarController::class, 'preview'])
        ->name('voyages.cars.import-preview');
    Route::post('/voyages/{voyage}/cars/import-confirm', [VoyageCarController::class, 'confirmImport'])
        ->name('voyages.cars.import-confirm');

    Route::post('/voyages/{voyage}/expenses', [VoyageExpenseController::class, 'store'])
        ->name('voyages.expenses.store');
    Route::put('/voyages/{voyage}/expenses/{expense}', [VoyageExpenseController::class, 'update'])
        ->name('voyages.expenses.update');
    Route::delete('/voyages/{voyage}/expenses/{expense}', [VoyageExpenseController::class, 'destroy'])
        ->name('voyages.expenses.destroy');
    Route::post('/voyages/{voyage}/expenses/{expense}/post', [VoyageExpenseController::class, 'post'])
        ->name('voyages.expenses.post');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/voyages', [ReportController::class, 'voyages'])->name('reports.voyages');
    Route::get('/reports/voyages/export/excel', [ReportController::class, 'exportVoyagesExcel'])
        ->name('reports.voyages.export.excel');
    Route::get('/reports/voyages/export/pdf', [ReportController::class, 'exportVoyagesPdf'])
        ->name('reports.voyages.export.pdf');

    Route::get('/journals', [JournalEntryController::class, 'index'])->name('journals.index');
    Route::get('/journals/create', [JournalEntryController::class, 'create'])->name('journals.create');
    Route::post('/journals', [JournalEntryController::class, 'store'])->name('journals.store');
    Route::get('/journals/{journal}/print', [JournalEntryController::class, 'print'])->name('journals.print');
    Route::get('/journals/{journal}', [JournalEntryController::class, 'show'])->name('journals.show');
    Route::get('/journals/{journal}/edit', [JournalEntryController::class, 'edit'])->name('journals.edit');
    Route::put('/journals/{journal}', [JournalEntryController::class, 'update'])->name('journals.update');
    Route::post('/journals/{journal}/post', [JournalEntryController::class, 'post'])->name('journals.post');
    Route::post('/journals/{journal}/void', [JournalEntryController::class, 'void'])->name('journals.void');

    Route::get('/money-vouchers', [MoneyVoucherController::class, 'index'])->name('money-vouchers.index');
    Route::get('/money-vouchers/create', [MoneyVoucherController::class, 'create'])->name('money-vouchers.create');
    Route::post('/money-vouchers', [MoneyVoucherController::class, 'store'])->name('money-vouchers.store');
    Route::get('/money-vouchers/{money_voucher}', [MoneyVoucherController::class, 'show'])->name('money-vouchers.show');
    Route::get('/money-vouchers/{money_voucher}/edit', [MoneyVoucherController::class, 'edit'])->name('money-vouchers.edit');
    Route::put('/money-vouchers/{money_voucher}', [MoneyVoucherController::class, 'update'])->name('money-vouchers.update');
    Route::delete('/money-vouchers/{money_voucher}', [MoneyVoucherController::class, 'destroy'])->name('money-vouchers.destroy');
    Route::post('/money-vouchers/{money_voucher}/post', [MoneyVoucherController::class, 'post'])->name('money-vouchers.post');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
