<?php

/*
|--------------------------------------------------------------------------
| Village City — permissions, default roles and navigation
|--------------------------------------------------------------------------
| Permission slugs are "<module>.<action>". Roles are seeded from the map
| below and can then be edited in Administration → Roles & Permissions.
*/

return [

    'permissions' => [
        'dashboard' => ['view'],
        'farmers' => ['view', 'manage'],
        'suppliers' => ['view', 'manage'],
        'centres' => ['view', 'manage'],
        'products' => ['view', 'manage'],
        'procurement' => ['view', 'manage'],
        'quality' => ['view', 'manage'],
        'inventory' => ['view', 'manage'],
        'marketplace' => ['view'],
        'customers' => ['view', 'manage'],
        'orders' => ['view', 'manage', 'own'],
        'invoices' => ['view'],
        'payments' => ['view', 'manage'],
        'logistics' => ['view', 'manage'],
        'expenses' => ['view', 'manage'],
        'settlements' => ['view', 'manage'],
        'finance' => ['view'],
        'reports' => ['view'],
        'intelligence' => ['view'],
        'users' => ['view', 'manage'],
        'roles' => ['view', 'manage'],
        'counties' => ['view', 'manage'],
        'settings' => ['manage'],
        'audit' => ['view'],
        'inquiries' => ['view'],
        'seo' => ['manage'],
    ],

    // Patterns: "*" = everything, "module.*" = all actions of a module, "*.view" = view on every module.
    'roles' => [
        'Super Administrator' => ['*'],
        'Administrator' => ['*'],
        'CEO' => ['*.view'],
        'Operations Manager' => ['*.view', 'farmers.manage', 'suppliers.manage', 'centres.manage', 'products.manage', 'procurement.manage', 'quality.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'logistics.manage'],
        'Procurement Officer' => ['dashboard.view', 'farmers.*', 'suppliers.*', 'products.view', 'centres.view', 'procurement.*', 'quality.view', 'inventory.view', 'intelligence.view', 'reports.view'],
        'County Coordinator' => ['dashboard.view', 'farmers.*', 'suppliers.*', 'centres.view', 'procurement.view', 'products.view', 'counties.view', 'reports.view'],
        'Aggregation Centre Officer' => ['dashboard.view', 'farmers.*', 'centres.view', 'procurement.*', 'quality.view', 'inventory.view', 'products.view'],
        'Logistics Manager' => ['dashboard.view', 'logistics.*', 'orders.view', 'centres.view', 'customers.view', 'reports.view'],
        'Finance Manager' => ['dashboard.view', 'invoices.view', 'payments.*', 'expenses.*', 'settlements.*', 'finance.view', 'reports.view', 'customers.view', 'orders.view', 'procurement.view'],
        'Sales Manager' => ['dashboard.view', 'inquiries.view', 'customers.*', 'orders.view', 'orders.manage', 'marketplace.view', 'invoices.view', 'payments.view', 'products.view', 'inventory.view', 'reports.view', 'intelligence.view'],
        'Warehouse Manager' => ['dashboard.view', 'inventory.*', 'centres.*', 'products.view', 'procurement.view', 'quality.view', 'orders.view', 'logistics.view'],
        'Quality Control Officer' => ['dashboard.view', 'quality.*', 'procurement.view', 'inventory.view', 'products.view'],
        'Content & SEO Manager' => ['dashboard.view', 'seo.manage', 'inquiries.view', 'products.view'],
        'Customer' => ['marketplace.view', 'orders.own'],
        'Farmer' => [],
    ],

    // Each item: [label, route name, icon, ability]
    'navigation' => [
        'Overview' => [
            ['Dashboard', 'dashboard', 'home', null],
        ],
        'Supply Chain' => [
            ['Farmers', 'farmers.index', 'users', 'farmers.view'],
            ['Suppliers', 'suppliers.index', 'building-storefront', 'suppliers.view'],
            ['Cooperatives', 'cooperatives.index', 'user-group', 'suppliers.view'],
            ['Collection Centres', 'centres.index', 'building-office-2', 'centres.view'],
            ['Products', 'products.index', 'cube', 'products.view'],
            ['Procurement', 'procurement.index', 'clipboard-document-list', 'procurement.view'],
            ['Inventory', 'inventory.index', 'archive-box', 'inventory.view'],
            ['Quality Control', 'quality.index', 'clipboard-document-check', 'quality.view'],
        ],
        'Commerce' => [
            ['Marketplace', 'marketplace', 'shopping-bag', 'marketplace.view'],
            ['Customers', 'customers.index', 'identification', 'customers.view'],
            ['Orders', 'orders.index', 'shopping-cart', 'orders.access'],
            ['Sales', 'sales.index', 'presentation-chart-line', 'orders.view'],
            ['Invoices', 'invoices.index', 'document-text', 'invoices.view'],
            ['Payments', 'payments.index', 'credit-card', 'payments.view'],
        ],
        'Logistics' => [
            ['Vehicles', 'vehicles.index', 'truck', 'logistics.view'],
            ['Transporters', 'transporters.index', 'briefcase', 'logistics.view'],
            ['Dispatch', 'dispatches.index', 'paper-airplane', 'logistics.view'],
            ['Deliveries', 'deliveries.index', 'map-pin', 'logistics.view'],
            ['Routes', 'routes.index', 'map', 'logistics.view'],
        ],
        'Finance' => [
            ['Expenses', 'expenses.index', 'receipt-percent', 'expenses.view'],
            ['Supplier Settlements', 'settlements.index', 'banknotes', 'settlements.view'],
            ['Financial Transactions', 'transactions.index', 'arrows-right-left', 'finance.view'],
            ['Reports', 'reports.index', 'chart-bar', 'reports.view'],
        ],
        'Intelligence' => [
            ['Analytics', 'analytics', 'chart-pie', 'intelligence.view'],
            ['Demand Forecasting', 'forecasting', 'arrow-trending-up', 'intelligence.view'],
            ['Market Intelligence', 'market', 'light-bulb', 'intelligence.view'],
        ],
        'Help' => [
            ['Help & Documentation', 'help.index', 'book-open', null],
        ],
        'Administration' => [
            ['Users', 'users.index', 'user-circle', 'users.view'],
            ['Roles & Permissions', 'roles.index', 'shield-check', 'roles.view'],
            ['Counties', 'counties.index', 'globe-europe-africa', 'counties.view'],
            ['Settings', 'system.settings', 'cog-6-tooth', 'settings.manage'],
            ['SEO & Site Content', 'seo.index', 'megaphone', 'seo.manage'],
            ['Website Inquiries', 'inquiries.index', 'envelope', 'inquiries.view'],
            ['Audit Logs', 'audit.index', 'finger-print', 'audit.view'],
        ],
    ],
];
