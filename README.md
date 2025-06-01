# Laravel QuickBooks Integration

A comprehensive Laravel package for seamless integration with QuickBooks Online, featuring OAuth 2.0 authentication, automatic data synchronization, and powerful scaffolding commands.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/e3-development-solutions/laravel-quickbooks-integration.svg?style=flat-square)](https://packagist.org/packages/e3-development-solutions/laravel-quickbooks-integration)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/e3-development-solutions/laravel-quickbooks-integration/run-tests?label=tests)](https://github.com/e3-development-solutions/laravel-quickbooks-integration/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/e3-development-solutions/laravel-quickbooks-integration/Check%20&%20fix%20styling?label=code%20style)](https://github.com/e3-development-solutions/laravel-quickbooks-integration/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/e3-development-solutions/laravel-quickbooks-integration.svg?style=flat-square)](https://packagist.org/packages/e3-development-solutions/laravel-quickbooks-integration)

## Features

- **🔐 OAuth 2.0 Authentication**: Secure QuickBooks Online integration with automatic token management
- **🔄 Automatic Synchronization**: Bidirectional sync between your Laravel app and QuickBooks
- **🛡️ Middleware Protection**: Route protection with automatic token validation and refresh
- **⚡ Scaffolding Commands**: Generate complete MVC structures for QuickBooks entities
- **🎨 Pre-built Views**: Beautiful, responsive UI components for QuickBooks integration
- **🧪 Comprehensive Testing**: Full test suite with 95%+ code coverage
- **📚 Rich Documentation**: Detailed guides and examples for every feature
- **🔧 Highly Configurable**: Extensive configuration options for any use case

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [QuickBooks App Setup](#quickbooks-app-setup)
- [Usage](#usage)
  - [OAuth Authentication](#oauth-authentication)
  - [Middleware Protection](#middleware-protection)
  - [Scaffolding Commands](#scaffolding-commands)
  - [Model Synchronization](#model-synchronization)
- [Advanced Features](#advanced-features)
- [Testing](#testing)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)




## Installation

### Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- QuickBooks Online account
- QuickBooks Developer App (see [QuickBooks App Setup](#quickbooks-app-setup))

### Install via Composer

```bash
composer require e3-development-solutions/laravel-quickbooks-integration
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="E3DevelopmentSolutions\LaravelQuickBooksIntegration\Providers\QuickBooksIntegrationServiceProvider"
```

This will publish:
- `config/quickbooks.php` - Main configuration file
- Database migrations for QuickBooks tokens
- View templates for OAuth flow

### Run Migrations

```bash
php artisan migrate
```

### Environment Configuration

Add the following variables to your `.env` file:

```env
# QuickBooks OAuth Configuration
QUICKBOOKS_CLIENT_ID=your_client_id
QUICKBOOKS_CLIENT_SECRET=your_client_secret
QUICKBOOKS_REDIRECT_URI=https://your-app.com/quickbooks/callback
QUICKBOOKS_SCOPE=com.intuit.quickbooks.accounting
QUICKBOOKS_ENVIRONMENT=sandbox  # or 'production'

# Optional: Token Encryption (recommended for production)
QUICKBOOKS_ENCRYPT_TOKENS=true

# Optional: Webhook Configuration
QUICKBOOKS_WEBHOOK_VERIFIER_TOKEN=your_webhook_verifier_token
```


## Configuration

The package configuration is located in `config/quickbooks.php`. Here are the key configuration options:

### OAuth Settings

```php
'oauth' => [
    'client_id' => env('QUICKBOOKS_CLIENT_ID'),
    'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
    'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
    'scope' => env('QUICKBOOKS_SCOPE', 'com.intuit.quickbooks.accounting'),
    'environment' => env('QUICKBOOKS_ENVIRONMENT', 'sandbox'), // 'sandbox' or 'production'
],
```

### Token Management

```php
'tokens' => [
    'encryption' => env('QUICKBOOKS_ENCRYPT_TOKENS', true),
    'auto_refresh' => true,
    'refresh_threshold_minutes' => 30, // Refresh tokens 30 minutes before expiry
],
```

### Middleware Configuration

```php
'middleware' => [
    'redirect_route' => 'quickbooks.connect',
    'success_route' => 'dashboard',
    'error_route' => 'quickbooks.error',
    'exclude_routes' => [
        'quickbooks.connect',
        'quickbooks.callback',
        'quickbooks.disconnect',
    ],
],
```

### Scaffolding Options

```php
'scaffolding' => [
    'model_namespace' => 'App\\Models\\QuickBooks',
    'controller_namespace' => 'App\\Http\\Controllers\\QuickBooks',
    'view_path' => 'quickbooks',
    'route_prefix' => 'quickbooks',
    'route_middleware' => ['web', 'auth', 'quickbooks.oauth'],
],
```

## QuickBooks App Setup

Before using this package, you need to create a QuickBooks Developer App:

### 1. Create a Developer Account

1. Visit [QuickBooks Developer](https://developer.intuit.com/)
2. Sign in with your Intuit account or create a new one
3. Navigate to "My Apps" in the developer dashboard

### 2. Create a New App

1. Click "Create an app"
2. Select "QuickBooks Online and Payments" as the platform
3. Choose your app type (usually "Web App")
4. Fill in your app details:
   - **App Name**: Your application name
   - **Description**: Brief description of your app
   - **Industry**: Select the most appropriate industry

### 3. Configure OAuth Settings

In your app's settings:

1. **Redirect URIs**: Add your callback URL
   ```
   https://your-domain.com/quickbooks/callback
   ```

2. **Scopes**: Select the required scopes
   - `com.intuit.quickbooks.accounting` (for accounting data)
   - `com.intuit.quickbooks.payment` (if using payments)

### 4. Get Your Credentials

From the app dashboard, copy:
- **Client ID** (App ID)
- **Client Secret**

### 5. Environment Configuration

For development, use the **Sandbox** environment:
- Base URL: `https://sandbox-quickbooks.api.intuit.com`
- Discovery Document: `https://appcenter.intuit.com/api/v1/OpenID_sandbox`

For production, use the **Production** environment:
- Base URL: `https://quickbooks.api.intuit.com`
- Discovery Document: `https://appcenter.intuit.com/api/v1/OpenID_production`

### 6. Webhook Configuration (Optional)

If you want to receive real-time updates:

1. In your app settings, configure webhooks
2. Set your webhook URL: `https://your-domain.com/quickbooks/webhooks`
3. Copy the **Verifier Token** for webhook signature validation


## Usage

### OAuth Authentication

The package provides a complete OAuth 2.0 flow for QuickBooks Online integration.

#### Basic Authentication Flow

1. **Redirect to QuickBooks**: Users are redirected to QuickBooks for authorization
2. **Authorization**: Users grant permission to your app
3. **Callback Handling**: QuickBooks redirects back with authorization code
4. **Token Exchange**: Package exchanges code for access/refresh tokens
5. **Token Storage**: Tokens are securely stored and encrypted

#### Using the Authentication Routes

The package automatically registers these routes:

```php
// Initiate QuickBooks connection
GET /quickbooks/connect

// Handle OAuth callback
GET /quickbooks/callback

// Check connection status
GET /quickbooks/status

// Disconnect from QuickBooks
POST /quickbooks/disconnect

// Refresh access token
POST /quickbooks/refresh-token
```

#### Programmatic Authentication

```php
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Facades\QuickBooks;

// Get authorization URL
$authUrl = QuickBooks::getAuthorizationUrl();
return redirect($authUrl);

// Check if user is connected
$isConnected = QuickBooks::isConnected(auth()->user());

// Get user's QuickBooks token
$token = QuickBooks::getUserToken(auth()->user());

// Get authenticated DataService
$dataService = QuickBooks::getDataService(auth()->user());
```

#### Custom Authentication Views

You can customize the authentication views by publishing them:

```bash
php artisan vendor:publish --tag=quickbooks-views
```

This publishes views to `resources/views/vendor/quickbooks/`:
- `connect.blade.php` - Connection interface
- `status.blade.php` - Connection status page
- `error.blade.php` - Error handling page

### Middleware Protection

Protect your routes with the QuickBooks OAuth middleware to ensure users have valid QuickBooks connections.

#### Applying Middleware

```php
// In your routes file
Route::middleware(['auth', 'quickbooks.oauth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::resource('/customers', CustomerController::class);
    Route::resource('/invoices', InvoiceController::class);
});

// Or in your controller constructor
class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'quickbooks.oauth']);
    }
}
```

#### Middleware Features

- **Automatic Token Validation**: Checks if tokens are valid and not expired
- **Token Refresh**: Automatically refreshes expired access tokens
- **Graceful Redirects**: Redirects to connection page when authentication is needed
- **AJAX Support**: Returns JSON responses for AJAX requests
- **Route Exclusions**: Skip middleware for specific routes

#### Accessing Token Information

In your controllers, the middleware adds token information to the request:

```php
public function index(Request $request)
{
    $token = $request->attributes->get('quickbooks_token');
    $realmId = $request->attributes->get('quickbooks_realm_id');
    
    // Your logic here
}
```

#### Middleware Configuration

Customize middleware behavior in `config/quickbooks.php`:

```php
'middleware' => [
    'redirect_route' => 'quickbooks.connect',
    'success_route' => 'dashboard',
    'error_route' => 'quickbooks.error',
    'exclude_routes' => [
        'quickbooks.*',
        'api.*',
    ],
],
```


### Scaffolding Commands

Generate complete MVC structures for QuickBooks entities with a single command.

#### Basic Scaffolding

```bash
# Generate complete MVC for Customer entity
php artisan quickbooks:scaffold Customer

# Generate with specific options
php artisan quickbooks:scaffold Invoice --force

# Skip specific components
php artisan quickbooks:scaffold Item --no-views --no-routes
```

#### What Gets Generated

The scaffolding command creates:

1. **Migration**: Database table with proper QuickBooks sync fields
2. **Model**: Eloquent model with QuickBooks synchronization traits
3. **Controller**: Full CRUD controller with sync methods
4. **Views**: Complete set of Blade templates (index, show, create, edit)
5. **Routes**: RESTful routes with middleware protection

#### Supported Entities

Configure supported entities in `config/quickbooks.php`:

```php
'entities' => [
    'Customer' => [
        'fields' => ['name', 'company_name', 'email', 'phone'],
        'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer',
    ],
    'Invoice' => [
        'fields' => ['doc_number', 'txn_date', 'due_date', 'total_amt'],
        'class' => 'QuickBooksOnline\\API\\Data\\IPPInvoice',
    ],
    'Item' => [
        'fields' => ['name', 'description', 'unit_price', 'type'],
        'class' => 'QuickBooksOnline\\API\\Data\\IPPItem',
    ],
],
```

#### Generated Model Example

```php
<?php

namespace App\Models\QuickBooks;

use Illuminate\Database\Eloquent\Model;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Traits\SyncsWithQuickBooks;

class Customer extends Model
{
    use SyncsWithQuickBooks;

    protected $table = 'quickbooks_customers';
    protected $quickbooksClass = IPPCustomer::class;

    protected $fillable = [
        'quickbooks_id', 'sync_token', 'name', 'company_name', 
        'email', 'phone', 'last_synced_at'
    ];

    // Automatic QuickBooks mapping methods
    protected function mapToQuickBooksEntity($entity): void
    {
        $entity->Name = $this->name;
        $entity->CompanyName = $this->company_name;
        // ... additional mapping
    }

    protected function mapFromQuickBooksEntity($entity): void
    {
        $this->name = $entity->Name;
        $this->company_name = $entity->CompanyName;
        // ... additional mapping
    }
}
```

#### Generated Controller Features

```php
// Automatic sync methods
public function sync(Customer $customer)
{
    $customer->syncToQuickBooks();
    return redirect()->back()->with('success', 'Synced successfully!');
}

public function syncAll()
{
    $count = Customer::syncAllFromQuickBooks(auth()->id());
    return redirect()->back()->with('success', "Synced {$count} customers!");
}
```

### Model Synchronization

The package provides powerful synchronization capabilities between your Laravel models and QuickBooks entities.

#### Synchronization Traits

Add the `SyncsWithQuickBooks` trait to your models:

```php
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Traits\SyncsWithQuickBooks;

class Customer extends Model
{
    use SyncsWithQuickBooks;
    
    protected $quickbooksClass = IPPCustomer::class;
}
```

#### Sync Operations

```php
// Sync single model to QuickBooks
$customer = Customer::find(1);
$customer->syncToQuickBooks();

// Sync all models from QuickBooks
$syncedCount = Customer::syncAllFromQuickBooks(auth()->id());

// Delete from QuickBooks
$customer->deleteFromQuickBooks();

// Check sync status
if ($customer->isSyncedWithQuickBooks()) {
    // Model is synced
}

if ($customer->needsSync()) {
    // Model has local changes that need syncing
}
```

#### Automatic Sync Hooks

```php
// In your model
protected static function booted()
{
    static::updated(function ($model) {
        if (config('quickbooks.auto_sync', false)) {
            $model->syncToQuickBooks();
        }
    });
}
```

#### Batch Synchronization

```php
// Sync multiple models
$customers = Customer::needsSync()->get();
foreach ($customers as $customer) {
    try {
        $customer->syncToQuickBooks();
    } catch (\Exception $e) {
        Log::error("Sync failed for customer {$customer->id}: " . $e->getMessage());
    }
}

// Sync with progress tracking
$customers = Customer::needsSync()->get();
$progressBar = $this->output->createProgressBar($customers->count());

foreach ($customers as $customer) {
    $customer->syncToQuickBooks();
    $progressBar->advance();
}

$progressBar->finish();
```

#### Handling Sync Conflicts

```php
// Custom conflict resolution
protected function handleSyncConflict($localModel, $quickbooksEntity)
{
    // Compare timestamps
    if ($localModel->updated_at > $quickbooksEntity->MetaData->LastUpdatedTime) {
        // Local is newer, push to QuickBooks
        return $this->syncToQuickBooks();
    } else {
        // QuickBooks is newer, pull from QuickBooks
        return $this->syncFromQuickBooks($quickbooksEntity);
    }
}
```


## Advanced Features

### Webhook Support

Handle real-time updates from QuickBooks using webhooks.

#### Webhook Configuration

```php
// In config/quickbooks.php
'webhooks' => [
    'enabled' => true,
    'verifier_token' => env('QUICKBOOKS_WEBHOOK_VERIFIER_TOKEN'),
    'endpoint' => '/quickbooks/webhooks',
],
```

#### Webhook Handler

```php
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Events\QuickBooksWebhookReceived;

// Listen for webhook events
Event::listen(QuickBooksWebhookReceived::class, function ($event) {
    foreach ($event->entities as $entity) {
        // Handle entity changes
        $this->handleEntityUpdate($entity);
    }
});
```

### Custom Entity Mapping

Create custom mappings for complex QuickBooks entities.

```php
class Invoice extends Model
{
    use SyncsWithQuickBooks;

    protected function mapToQuickBooksEntity($entity): void
    {
        $entity->CustomerRef = $this->createCustomerReference();
        $entity->Line = $this->createLineItems();
        $entity->TxnDate = $this->txn_date->format('Y-m-d');
        
        // Custom business logic
        if ($this->is_recurring) {
            $entity->RecurDataRef = $this->createRecurringReference();
        }
    }

    private function createLineItems(): array
    {
        return $this->lineItems->map(function ($item) {
            $line = new IPPLine();
            $line->Amount = $item->amount;
            $line->DetailType = 'SalesItemLineDetail';
            
            $line->SalesItemLineDetail = new IPPSalesItemLineDetail();
            $line->SalesItemLineDetail->ItemRef = $item->createItemReference();
            
            return $line;
        })->toArray();
    }
}
```

### Error Handling and Logging

Comprehensive error handling with detailed logging.

```php
// Custom error handling
try {
    $customer->syncToQuickBooks();
} catch (ServiceException $e) {
    // QuickBooks API errors
    Log::error('QuickBooks API Error', [
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage(),
        'customer_id' => $customer->id,
    ]);
} catch (ValidationException $e) {
    // Data validation errors
    Log::warning('Validation Error', [
        'errors' => $e->getErrors(),
        'customer_data' => $customer->toArray(),
    ]);
}

// Global error handler
QuickBooks::onError(function ($exception, $context) {
    // Send to error tracking service
    app('sentry')->captureException($exception, $context);
    
    // Notify administrators
    Mail::to('admin@example.com')->send(new QuickBooksErrorMail($exception));
});
```

### Performance Optimization

#### Batch Operations

```php
// Batch sync for better performance
$customers = Customer::needsSync()->chunk(50, function ($customers) {
    foreach ($customers as $customer) {
        dispatch(new SyncCustomerJob($customer));
    }
});

// Rate limiting
QuickBooks::rateLimit(function () {
    // Sync operations with automatic rate limiting
    return Customer::syncAllFromQuickBooks(auth()->id());
});
```

#### Caching

```php
// Cache QuickBooks data
$items = Cache::remember('quickbooks.items.' . auth()->id(), 3600, function () {
    return QuickBooks::getItems(auth()->user());
});

// Cache with tags for easy invalidation
Cache::tags(['quickbooks', 'user.' . auth()->id()])->put('customers', $customers, 3600);

// Invalidate cache on sync
$customer->syncToQuickBooks();
Cache::tags(['quickbooks', 'user.' . auth()->id()])->flush();
```

### Multi-Company Support

Handle multiple QuickBooks companies for a single user.

```php
// Company-specific tokens
$token = QuickBooksToken::where('user_id', auth()->id())
    ->where('realm_id', $companyId)
    ->first();

// Company-specific models
class Customer extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('company', function ($query) {
            if ($companyId = session('quickbooks.company_id')) {
                $query->whereHas('token', function ($q) use ($companyId) {
                    $q->where('realm_id', $companyId);
                });
            }
        });
    }
}
```

## Testing

The package includes a comprehensive test suite.

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific test suite
vendor/bin/phpunit tests/Unit/QuickBooksServiceTest.php
```

### Testing Your Integration

```php
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Testing\QuickBooksFake;

class CustomerSyncTest extends TestCase
{
    public function test_customer_syncs_to_quickbooks()
    {
        QuickBooksFake::fake();
        
        $customer = Customer::factory()->create();
        $customer->syncToQuickBooks();
        
        QuickBooksFake::assertSynced($customer);
    }
    
    public function test_webhook_handling()
    {
        QuickBooksFake::fake();
        
        $this->postJson('/quickbooks/webhooks', [
            'eventNotifications' => [
                ['realmId' => '123', 'dataChangeEvent' => [/* ... */]]
            ]
        ]);
        
        QuickBooksFake::assertWebhookReceived();
    }
}
```

### Mock QuickBooks Responses

```php
// Mock successful responses
QuickBooksFake::shouldReturn([
    'customers' => Customer::factory()->count(5)->make(),
    'invoices' => Invoice::factory()->count(10)->make(),
]);

// Mock errors
QuickBooksFake::shouldThrow(new ServiceException('Rate limit exceeded'));
```

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/e3-development-solutions/laravel-quickbooks-integration.git

# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer lint

# Fix code style
composer fix-style
```

### Contribution Guidelines

- Follow PSR-12 coding standards
- Write tests for new features
- Update documentation for any changes
- Use conventional commit messages
- Ensure all tests pass before submitting PR

## Security

If you discover any security-related issues, please email security@e3developmentsolutions.com instead of using the issue tracker.

### Security Features

- **Token Encryption**: All tokens are encrypted at rest
- **Secure Storage**: Tokens stored with proper database security
- **CSRF Protection**: All forms include CSRF protection
- **Rate Limiting**: Built-in rate limiting for API calls
- **Webhook Verification**: Webhook signatures are verified
- **Input Validation**: All inputs are validated and sanitized

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [E3 Development Solutions](https://github.com/e3-development-solutions)
- [All Contributors](../../contributors)

## Support

- **Documentation**: [Full Documentation](https://docs.e3developmentsolutions.com/laravel-quickbooks-integration)
- **Issues**: [GitHub Issues](https://github.com/e3-development-solutions/laravel-quickbooks-integration/issues)
- **Discussions**: [GitHub Discussions](https://github.com/e3-development-solutions/laravel-quickbooks-integration/discussions)
- **Email**: support@e3developmentsolutions.com

---

Made with ❤️ by [E3 Development Solutions](https://e3developmentsolutions.com)

