<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QuickBooks OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains all the settings needed for QuickBooks
    | Online integration using OAuth 2.0 authentication.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0 Settings
    |--------------------------------------------------------------------------
    |
    | These settings are required for OAuth 2.0 authentication with QuickBooks.
    | You can find these values in your QuickBooks app dashboard at
    | https://developer.intuit.com
    |
    */
    'oauth' => [
        'client_id' => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI', '/quickbooks/callback'),
        'scope' => env('QUICKBOOKS_SCOPE', 'com.intuit.quickbooks.accounting'),
        'base_url' => env('QUICKBOOKS_BASE_URL', 'Development'), // Development or Production
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for storing QuickBooks tokens and company information
    | in your application's database.
    |
    */
    'database' => [
        'users_table' => env('QUICKBOOKS_USERS_TABLE', 'users'),
        'tokens_table' => env('QUICKBOOKS_TOKENS_TABLE', 'quickbooks_tokens'),
        'connection' => env('QUICKBOOKS_DB_CONNECTION', null), // Use default connection if null
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Management
    |--------------------------------------------------------------------------
    |
    | Settings for managing OAuth tokens including expiration handling
    | and refresh behavior.
    |
    */
    'tokens' => [
        'access_token_lifetime' => env('QUICKBOOKS_ACCESS_TOKEN_LIFETIME', 3600), // 1 hour in seconds
        'refresh_token_lifetime' => env('QUICKBOOKS_REFRESH_TOKEN_LIFETIME', 8726400), // 101 days in seconds
        'auto_refresh' => env('QUICKBOOKS_AUTO_REFRESH', true),
        'encryption' => env('QUICKBOOKS_TOKEN_ENCRYPTION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for QuickBooks API requests including timeouts and retries.
    |
    */
    'api' => [
        'timeout' => env('QUICKBOOKS_API_TIMEOUT', 30),
        'retry_attempts' => env('QUICKBOOKS_API_RETRY_ATTEMPTS', 3),
        'minor_version' => env('QUICKBOOKS_MINOR_VERSION', '65'),
        'log_requests' => env('QUICKBOOKS_LOG_REQUESTS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the QuickBooks OAuth middleware behavior.
    |
    */
    'middleware' => [
        'redirect_route' => env('QUICKBOOKS_REDIRECT_ROUTE', 'quickbooks.connect'),
        'error_route' => env('QUICKBOOKS_ERROR_ROUTE', 'quickbooks.error'),
        'success_route' => env('QUICKBOOKS_SUCCESS_ROUTE', 'dashboard'),
        'exclude_routes' => [
            'quickbooks.connect',
            'quickbooks.callback',
            'quickbooks.disconnect',
            'quickbooks.error',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scaffolding Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the scaffolding command that generates models, controllers,
    | and views for QuickBooks entities.
    |
    */
    'scaffolding' => [
        'namespace' => env('QUICKBOOKS_SCAFFOLD_NAMESPACE', 'App\\QuickBooks'),
        'controller_namespace' => env('QUICKBOOKS_CONTROLLER_NAMESPACE', 'App\\Http\\Controllers\\QuickBooks'),
        'model_namespace' => env('QUICKBOOKS_MODEL_NAMESPACE', 'App\\Models\\QuickBooks'),
        'view_path' => env('QUICKBOOKS_VIEW_PATH', 'quickbooks'),
        'route_prefix' => env('QUICKBOOKS_ROUTE_PREFIX', 'quickbooks'),
        'route_middleware' => ['web', 'auth', 'quickbooks.oauth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported QuickBooks Entities
    |--------------------------------------------------------------------------
    |
    | List of QuickBooks entities that can be scaffolded. Each entity
    | includes its class name and supported operations.
    |
    */
    'entities' => [
        'Customer' => [
            'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer',
            'operations' => ['create', 'read', 'update', 'delete', 'query'],
            'fields' => ['Name', 'CompanyName', 'BillAddr', 'ShipAddr', 'Phone', 'Email'],
        ],
        'Item' => [
            'class' => 'QuickBooksOnline\\API\\Data\\IPPItem',
            'operations' => ['create', 'read', 'update', 'delete', 'query'],
            'fields' => ['Name', 'Description', 'UnitPrice', 'Type', 'IncomeAccountRef'],
        ],
        'Invoice' => [
            'class' => 'QuickBooksOnline\\API\\Data\\IPPInvoice',
            'operations' => ['create', 'read', 'update', 'delete', 'query', 'send'],
            'fields' => ['CustomerRef', 'TxnDate', 'DueDate', 'Line', 'TotalAmt'],
        ],
        'Payment' => [
            'class' => 'QuickBooksOnline\\API\\Data\\IPPPayment',
            'operations' => ['create', 'read', 'update', 'delete', 'query'],
            'fields' => ['CustomerRef', 'TotalAmt', 'TxnDate', 'PaymentRefNum'],
        ],
        'Vendor' => [
            'class' => 'QuickBooksOnline\\API\\Data\\IPPVendor',
            'operations' => ['create', 'read', 'update', 'delete', 'query'],
            'fields' => ['Name', 'CompanyName', 'BillAddr', 'Phone', 'Email'],
        ],
        'Bill' => [
            'class' => 'QuickBooksOnline\\API\\Data\\IPPBill',
            'operations' => ['create', 'read', 'update', 'delete', 'query'],
            'fields' => ['VendorRef', 'TxnDate', 'DueDate', 'Line', 'TotalAmt'],
        ],
        'Account' => [
            'class' => 'QuickBooksOnline\\API\\Data\\IPPAccount',
            'operations' => ['create', 'read', 'update', 'query'],
            'fields' => ['Name', 'AccountType', 'AccountSubType', 'Description'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configuration for error handling and user-friendly error messages.
    |
    */
    'errors' => [
        'show_detailed_errors' => env('QUICKBOOKS_SHOW_DETAILED_ERRORS', false),
        'log_errors' => env('QUICKBOOKS_LOG_ERRORS', true),
        'error_view' => env('QUICKBOOKS_ERROR_VIEW', 'quickbooks::errors.general'),
        'messages' => [
            'token_expired' => 'Your QuickBooks connection has expired. Please reconnect to continue.',
            'token_invalid' => 'Invalid QuickBooks token. Please reconnect to your QuickBooks account.',
            'connection_failed' => 'Failed to connect to QuickBooks. Please try again later.',
            'api_error' => 'An error occurred while communicating with QuickBooks. Please try again.',
            'unauthorized' => 'You are not authorized to access QuickBooks data.',
        ],
    ],
];

