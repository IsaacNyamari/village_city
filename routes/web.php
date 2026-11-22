<?php

use App\Livewire\Dashboard;
use App\Livewire\Pages;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public website
Route::get('/', [\App\Http\Controllers\SiteController::class, 'home'])->name('home');
Route::get('about', [\App\Http\Controllers\SiteController::class, 'about'])->name('site.about');
Route::get('produce', \App\Livewire\Site\Catalogue::class)->name('site.products');
Route::get('produce/category/{category:slug}', \App\Livewire\Site\CategoryPage::class)->name('site.category');
Route::get('produce/{product:slug}', \App\Livewire\Site\ProductPage::class)->name('site.product');
Route::get('market-today', \App\Livewire\Site\MarketToday::class)->name('site.market');
Route::get('faq',\App\Livewire\Site\FaqPage::class)->name('site.faq');
Route::get('contact', \App\Livewire\Site\Contact::class)->name('site.contact');
Route::get('sell-with-us', \App\Livewire\Site\FarmerSignup::class)->name('site.sell');

// Search engine files
Route::get('sitemap.xml', [\App\Http\Controllers\SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('robots.txt', [\App\Http\Controllers\SeoController::class, 'robots'])->name('seo.robots');
Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');

    // Supply chain
    Route::get('farmers', Pages\Farmers::class)->name('farmers.index');
    Route::get('farmers/{farmer}', Pages\FarmerShow::class)->name('farmers.show');
    Route::get('suppliers', Pages\Suppliers::class)->name('suppliers.index');
    Route::get('suppliers/{supplier}', Pages\SupplierShow::class)->name('suppliers.show');
    Route::get('cooperatives', Pages\Cooperatives::class)->name('cooperatives.index');
    Route::get('centres', Pages\Centres::class)->name('centres.index');
    Route::get('centres/{centre}', Pages\CentreShow::class)->name('centres.show');
    Route::get('products', Pages\Products::class)->name('products.index');
    Route::get('products-categories', Pages\Categories::class)->name('products.categories');
    Route::get('procurement', Pages\Procurements::class)->name('procurement.index');
    Route::get('procurement/{procurement}', Pages\ProcurementShow::class)->name('procurement.show');
    Route::get('inventory', Pages\Inventory::class)->name('inventory.index');
    Route::get('quality-control', Pages\QualityControl::class)->name('quality.index');
    Route::get('daily-availability', Pages\DailyListings::class)->name('listings.index');
    Route::get('inspectors', Pages\Inspectors::class)->name('inspectors.index');
    Route::get('dispatch-signoff', Pages\DispatchChecks::class)->name('checks.index');
    Route::get('aggregation', Pages\Aggregation::class)->name('aggregation.index');
    Route::get('consignments', Pages\Consignments::class)->name('consignments.index');
    Route::get('fees', Pages\Fees::class)->name('fees.index');

    // Commerce
    Route::get('marketplace', Pages\Marketplace::class)->name('marketplace');
    Route::get('customers', Pages\Customers::class)->name('customers.index');
    Route::get('customers/{customer}', Pages\CustomerShow::class)->name('customers.show');
    Route::get('orders', Pages\Orders::class)->name('orders.index');
    Route::get('orders/{order}', Pages\OrderShow::class)->name('orders.show');
    Route::get('sales', Pages\Sales::class)->name('sales.index');
    Route::get('invoices', Pages\Invoices::class)->name('invoices.index');
    Route::get('invoices/{invoice}', Pages\InvoiceShow::class)->name('invoices.show');
    Route::get('payments', Pages\Payments::class)->name('payments.index');

    // Logistics
    Route::get('vehicles', Pages\Vehicles::class)->name('vehicles.index');
    Route::get('transporters', Pages\Transporters::class)->name('transporters.index');
    Route::get('dispatches', Pages\Dispatches::class)->name('dispatches.index');
    Route::get('deliveries', Pages\Deliveries::class)->name('deliveries.index');
    Route::get('deliveries/{delivery}', Pages\DeliveryShow::class)->name('deliveries.show');
    Route::get('routes', Pages\Routes::class)->name('routes.index');

    // Finance
    Route::get('expenses', Pages\Expenses::class)->name('expenses.index');
    Route::get('settlements', Pages\Settlements::class)->name('settlements.index');
    Route::get('transactions', Pages\Transactions::class)->name('transactions.index');
    Route::get('reports', Pages\Reports::class)->name('reports.index');

    // Intelligence
    Route::get('analytics', Pages\Analytics::class)->name('analytics');
    Route::get('forecasting', Pages\Forecasting::class)->name('forecasting');
    Route::get('market-intelligence', Pages\MarketIntelligence::class)->name('market');

    // Administration
    Route::get('users', Pages\Users::class)->name('users.index');
    Route::get('roles', Pages\Roles::class)->name('roles.index');
    Route::get('counties', Pages\Counties::class)->name('counties.index');
    Route::get('system-settings', Pages\SystemSettings::class)->name('system.settings');
    Route::get('audit-logs', Pages\AuditLogs::class)->name('audit.index');
    Route::get('inquiries', Pages\Inquiries::class)->name('inquiries.index');
    Route::get('seo-content', Pages\Seo::class)->name('seo.index');

    // Help & documentation (every signed-in user)
    Route::get('help', Pages\Help::class)->name('help.index');
    Route::get('help/{doc}', Pages\Help::class)->name('help.show');

    // My account
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
});

require __DIR__ . '/auth.php';
