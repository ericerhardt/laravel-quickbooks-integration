<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class ScaffoldQuickBooksModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quickbooks:scaffold {model : The QuickBooks model to scaffold}
                            {--force : Overwrite existing files}
                            {--no-migration : Skip migration generation}
                            {--no-controller : Skip controller generation}
                            {--no-views : Skip view generation}
                            {--no-routes : Skip route generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scaffold a complete MVC structure for a QuickBooks entity';

    /**
     * Supported QuickBooks entities configuration.
     *
     * @var array
     */
    protected $supportedEntities;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->supportedEntities = config('quickbooks.entities', []);
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelName = $this->argument('model');
        $modelName = Str::studly($modelName);

        // Validate the model
        if (!$this->isValidQuickBooksEntity($modelName)) {
            $this->error("'{$modelName}' is not a supported QuickBooks entity.");
            $this->info('Supported entities: ' . implode(', ', array_keys($this->supportedEntities)));
            return self::FAILURE;
        }

        $this->info("Scaffolding QuickBooks {$modelName}...");

        try {
            // Get entity configuration
            $entityConfig = $this->supportedEntities[$modelName];

            // Generate components
            $this->generateMigration($modelName, $entityConfig);
            $this->generateModel($modelName, $entityConfig);
            $this->generateController($modelName, $entityConfig);
            $this->generateViews($modelName, $entityConfig);
            $this->generateRoutes($modelName);

            $this->info("✅ Successfully scaffolded QuickBooks {$modelName}!");
            $this->displayNextSteps($modelName);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to scaffold {$modelName}: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Check if the entity is a valid QuickBooks entity.
     *
     * @param string $modelName
     * @return bool
     */
    protected function isValidQuickBooksEntity(string $modelName): bool
    {
        return array_key_exists($modelName, $this->supportedEntities);
    }

    /**
     * Generate migration for the QuickBooks entity.
     *
     * @param string $modelName
     * @param array $entityConfig
     */
    protected function generateMigration(string $modelName, array $entityConfig): void
    {
        if ($this->option('no-migration')) {
            return;
        }

        $tableName = Str::snake(Str::plural($modelName));
        $migrationName = "create_quickbooks_{$tableName}_table";
        
        $migrationPath = $this->getMigrationPath($migrationName);

        if (File::exists($migrationPath) && !$this->option('force')) {
            $this->warn("Migration already exists: {$migrationName}");
            return;
        }

        $migrationContent = $this->generateMigrationContent($modelName, $entityConfig, $tableName);
        
        File::put($migrationPath, $migrationContent);
        $this->info("✓ Created migration: {$migrationName}");
    }

    /**
     * Generate model for the QuickBooks entity.
     *
     * @param string $modelName
     * @param array $entityConfig
     */
    protected function generateModel(string $modelName, array $entityConfig): void
    {
        $namespace = config('quickbooks.scaffolding.model_namespace', 'App\\Models\\QuickBooks');
        $modelPath = $this->getModelPath($modelName);

        if (File::exists($modelPath) && !$this->option('force')) {
            $this->warn("Model already exists: {$modelName}");
            return;
        }

        $this->ensureDirectoryExists(dirname($modelPath));

        $modelContent = $this->generateModelContent($modelName, $entityConfig, $namespace);
        
        File::put($modelPath, $modelContent);
        $this->info("✓ Created model: {$namespace}\\{$modelName}");
    }

    /**
     * Generate controller for the QuickBooks entity.
     *
     * @param string $modelName
     * @param array $entityConfig
     */
    protected function generateController(string $modelName, array $entityConfig): void
    {
        if ($this->option('no-controller')) {
            return;
        }

        $controllerName = "{$modelName}Controller";
        $namespace = config('quickbooks.scaffolding.controller_namespace', 'App\\Http\\Controllers\\QuickBooks');
        $controllerPath = $this->getControllerPath($controllerName);

        if (File::exists($controllerPath) && !$this->option('force')) {
            $this->warn("Controller already exists: {$controllerName}");
            return;
        }

        $this->ensureDirectoryExists(dirname($controllerPath));

        $controllerContent = $this->generateControllerContent($modelName, $entityConfig, $namespace);
        
        File::put($controllerPath, $controllerContent);
        $this->info("✓ Created controller: {$namespace}\\{$controllerName}");
    }

    /**
     * Generate views for the QuickBooks entity.
     *
     * @param string $modelName
     * @param array $entityConfig
     */
    protected function generateViews(string $modelName, array $entityConfig): void
    {
        if ($this->option('no-views')) {
            return;
        }

        $viewPath = config('quickbooks.scaffolding.view_path', 'quickbooks');
        $entityViews = Str::snake(Str::plural($modelName));
        $viewsDirectory = resource_path("views/{$viewPath}/{$entityViews}");

        $this->ensureDirectoryExists($viewsDirectory);

        $views = ['index', 'show', 'create', 'edit'];
        
        foreach ($views as $view) {
            $viewFile = "{$viewsDirectory}/{$view}.blade.php";
            
            if (File::exists($viewFile) && !$this->option('force')) {
                $this->warn("View already exists: {$view}.blade.php");
                continue;
            }

            $viewContent = $this->generateViewContent($modelName, $entityConfig, $view);
            File::put($viewFile, $viewContent);
            $this->info("✓ Created view: {$viewPath}/{$entityViews}/{$view}.blade.php");
        }
    }

    /**
     * Generate routes for the QuickBooks entity.
     *
     * @param string $modelName
     */
    protected function generateRoutes(string $modelName): void
    {
        if ($this->option('no-routes')) {
            return;
        }

        $routePrefix = config('quickbooks.scaffolding.route_prefix', 'quickbooks');
        $routeMiddleware = config('quickbooks.scaffolding.route_middleware', ['web', 'auth', 'quickbooks.oauth']);
        
        $routesPath = base_path('routes/web.php');
        $routeContent = $this->generateRouteContent($modelName, $routePrefix, $routeMiddleware);

        // Check if routes already exist
        if (File::exists($routesPath) && str_contains(File::get($routesPath), "Route::resource('{$routePrefix}/" . Str::kebab(Str::plural($modelName)) . "'")) {
            $this->warn("Routes may already exist for {$modelName}");
            return;
        }

        File::append($routesPath, "\n" . $routeContent);
        $this->info("✓ Added routes for {$modelName}");
    }

    /**
     * Generate migration content.
     *
     * @param string $modelName
     * @param array $entityConfig
     * @param string $tableName
     * @return string
     */
    protected function generateMigrationContent(string $modelName, array $entityConfig, string $tableName): string
    {
        $className = 'Create' . Str::studly($tableName) . 'Table';
        $fields = $entityConfig['fields'] ?? [];

        $fieldsCode = '';
        foreach ($fields as $field) {
            $columnName = Str::snake($field);
            $fieldsCode .= "            \$table->string('{$columnName}')->nullable();\n";
        }

        return "<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('user_id')->constrained()->onDelete('cascade');
            \$table->string('quickbooks_id')->nullable();
            \$table->string('sync_token')->nullable();
{$fieldsCode}            \$table->timestamp('last_synced_at')->nullable();
            \$table->timestamps();

            \$table->index(['user_id', 'quickbooks_id']);
            \$table->unique(['user_id', 'quickbooks_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
";
    }

    /**
     * Generate model content.
     *
     * @param string $modelName
     * @param array $entityConfig
     * @param string $namespace
     * @return string
     */
    protected function generateModelContent(string $modelName, array $entityConfig, string $namespace): string
    {
        $tableName = Str::snake(Str::plural($modelName));
        $fields = $entityConfig['fields'] ?? [];
        $quickbooksClass = $entityConfig['class'] ?? '';

        $fillableFields = array_merge(['quickbooks_id', 'sync_token'], array_map(fn($field) => Str::snake($field), $fields));
        $fillableString = "'" . implode("',\n        '", $fillableFields) . "'";

        return "<?php

namespace {$namespace};

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use E3DevelopmentSolutions\\LaravelQuickBooksIntegration\\Traits\\SyncsWithQuickBooks;

class {$modelName} extends Model
{
    use SyncsWithQuickBooks;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected \$table = '{$tableName}';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected \$fillable = [
        {$fillableString},
        'last_synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected \$casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * QuickBooks entity class name.
     *
     * @var string
     */
    protected \$quickbooksClass = '{$quickbooksClass}';

    /**
     * Get the user that owns this {$modelName}.
     */
    public function user(): BelongsTo
    {
        return \$this->belongsTo(config('auth.providers.users.model', 'App\\\\Models\\\\User'));
    }

    /**
     * Scope a query to only include records for a specific user.
     *
     * @param \\Illuminate\\Database\\Eloquent\\Builder \$query
     * @param int \$userId
     * @return \\Illuminate\\Database\\Eloquent\\Builder
     */
    public function scopeForUser(\$query, int \$userId)
    {
        return \$query->where('user_id', \$userId);
    }

    /**
     * Scope a query to only include synced records.
     *
     * @param \\Illuminate\\Database\\Eloquent\\Builder \$query
     * @return \\Illuminate\\Database\\Eloquent\\Builder
     */
    public function scopeSynced(\$query)
    {
        return \$query->whereNotNull('quickbooks_id');
    }

    /**
     * Scope a query to only include unsynced records.
     *
     * @param \\Illuminate\\Database\\Eloquent\\Builder \$query
     * @return \\Illuminate\\Database\\Eloquent\\Builder
     */
    public function scopeUnsynced(\$query)
    {
        return \$query->whereNull('quickbooks_id');
    }
}
";
    }

    /**
     * Generate controller content.
     *
     * @param string $modelName
     * @param array $entityConfig
     * @param string $namespace
     * @return string
     */
    protected function generateControllerContent(string $modelName, array $entityConfig, string $namespace): string
    {
        $modelNamespace = config('quickbooks.scaffolding.model_namespace', 'App\\Models\\QuickBooks');
        $viewPath = config('quickbooks.scaffolding.view_path', 'quickbooks');
        $entityViews = Str::snake(Str::plural($modelName));
        $variableName = Str::camel($modelName);
        $pluralVariable = Str::camel(Str::plural($modelName));

        return "<?php

namespace {$namespace};

use Illuminate\\Http\\Request;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Routing\\Controller;
use Illuminate\\View\\View;
use Illuminate\\Support\\Facades\\Auth;
use {$modelNamespace}\\{$modelName};

class {$modelName}Controller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request \$request): View
    {
        \${$pluralVariable} = {$modelName}::forUser(Auth::id())
            ->latest()
            ->paginate(15);

        return view('{$viewPath}.{$entityViews}.index', compact('{$pluralVariable}'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('{$viewPath}.{$entityViews}.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request \$request): RedirectResponse
    {
        \$validated = \$request->validate([
            // Add validation rules here
        ]);

        \$validated['user_id'] = Auth::id();

        \${$variableName} = {$modelName}::create(\$validated);

        // Sync with QuickBooks
        try {
            \${$variableName}->syncToQuickBooks();
            \$message = '{$modelName} created and synced with QuickBooks successfully.';
        } catch (\\Exception \$e) {
            \$message = '{$modelName} created locally. Sync with QuickBooks failed: ' . \$e->getMessage();
        }

        return redirect()
            ->route('{$viewPath}.{$entityViews}.show', \${$variableName})
            ->with('success', \$message);
    }

    /**
     * Display the specified resource.
     */
    public function show({$modelName} \${$variableName}): View
    {
        \$this->authorize('view', \${$variableName});

        return view('{$viewPath}.{$entityViews}.show', compact('{$variableName}'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit({$modelName} \${$variableName}): View
    {
        \$this->authorize('update', \${$variableName});

        return view('{$viewPath}.{$entityViews}.edit', compact('{$variableName}'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request \$request, {$modelName} \${$variableName}): RedirectResponse
    {
        \$this->authorize('update', \${$variableName});

        \$validated = \$request->validate([
            // Add validation rules here
        ]);

        \${$variableName}->update(\$validated);

        // Sync with QuickBooks
        try {
            \${$variableName}->syncToQuickBooks();
            \$message = '{$modelName} updated and synced with QuickBooks successfully.';
        } catch (\\Exception \$e) {
            \$message = '{$modelName} updated locally. Sync with QuickBooks failed: ' . \$e->getMessage();
        }

        return redirect()
            ->route('{$viewPath}.{$entityViews}.show', \${$variableName})
            ->with('success', \$message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy({$modelName} \${$variableName}): RedirectResponse
    {
        \$this->authorize('delete', \${$variableName});

        // Delete from QuickBooks first
        try {
            \${$variableName}->deleteFromQuickBooks();
            \$message = '{$modelName} deleted from QuickBooks and locally.';
        } catch (\\Exception \$e) {
            \$message = '{$modelName} deleted locally. QuickBooks deletion failed: ' . \$e->getMessage();
        }

        \${$variableName}->delete();

        return redirect()
            ->route('{$viewPath}.{$entityViews}.index')
            ->with('success', \$message);
    }

    /**
     * Sync with QuickBooks.
     */
    public function sync({$modelName} \${$variableName}): RedirectResponse
    {
        \$this->authorize('update', \${$variableName});

        try {
            \${$variableName}->syncToQuickBooks();
            \$message = '{$modelName} synced with QuickBooks successfully.';
        } catch (\\Exception \$e) {
            \$message = 'Sync failed: ' . \$e->getMessage();
        }

        return redirect()
            ->route('{$viewPath}.{$entityViews}.show', \${$variableName})
            ->with('success', \$message);
    }

    /**
     * Sync all records from QuickBooks.
     */
    public function syncAll(): RedirectResponse
    {
        try {
            \$count = {$modelName}::syncAllFromQuickBooks(Auth::id());
            \$message = \"Synced {\$count} {$pluralVariable} from QuickBooks.\";
        } catch (\\Exception \$e) {
            \$message = 'Sync failed: ' . \$e->getMessage();
        }

        return redirect()
            ->route('{$viewPath}.{$entityViews}.index')
            ->with('success', \$message);
    }
}
";
    }

    /**
     * Generate view content.
     *
     * @param string $modelName
     * @param array $entityConfig
     * @param string $viewType
     * @return string
     */
    protected function generateViewContent(string $modelName, array $entityConfig, string $viewType): string
    {
        $viewPath = config('quickbooks.scaffolding.view_path', 'quickbooks');
        $entityViews = Str::snake(Str::plural($modelName));
        $variableName = Str::camel($modelName);
        $pluralVariable = Str::camel(Str::plural($modelName));
        $fields = $entityConfig['fields'] ?? [];

        return match ($viewType) {
            'index' => $this->generateIndexView($modelName, $fields, $viewPath, $entityViews, $pluralVariable),
            'show' => $this->generateShowView($modelName, $fields, $viewPath, $entityViews, $variableName),
            'create' => $this->generateCreateView($modelName, $fields, $viewPath, $entityViews),
            'edit' => $this->generateEditView($modelName, $fields, $viewPath, $entityViews, $variableName),
            default => '',
        };
    }

    /**
     * Generate route content.
     *
     * @param string $modelName
     * @param string $routePrefix
     * @param array $routeMiddleware
     * @return string
     */
    protected function generateRouteContent(string $modelName, string $routePrefix, array $routeMiddleware): string
    {
        $controllerNamespace = config('quickbooks.scaffolding.controller_namespace', 'App\\Http\\Controllers\\QuickBooks');
        $routeName = Str::kebab(Str::plural($modelName));
        $middlewareString = "'" . implode("', '", $routeMiddleware) . "'";

        return "
// QuickBooks {$modelName} Routes
Route::middleware([{$middlewareString}])->prefix('{$routePrefix}')->name('{$routePrefix}.')->group(function () {
    Route::resource('{$routeName}', {$controllerNamespace}\\{$modelName}Controller::class);
    Route::post('{$routeName}/{{" . Str::camel($modelName) . "}}/sync', [{$controllerNamespace}\\{$modelName}Controller::class, 'sync'])->name('{$routeName}.sync');
    Route::post('{$routeName}/sync-all', [{$controllerNamespace}\\{$modelName}Controller::class, 'syncAll'])->name('{$routeName}.sync-all');
});";
    }

    /**
     * Get migration path.
     *
     * @param string $migrationName
     * @return string
     */
    protected function getMigrationPath(string $migrationName): string
    {
        $timestamp = date('Y_m_d_His');
        return database_path("migrations/{$timestamp}_{$migrationName}.php");
    }

    /**
     * Get model path.
     *
     * @param string $modelName
     * @return string
     */
    protected function getModelPath(string $modelName): string
    {
        return app_path("Models/QuickBooks/{$modelName}.php");
    }

    /**
     * Get controller path.
     *
     * @param string $controllerName
     * @return string
     */
    protected function getControllerPath(string $controllerName): string
    {
        return app_path("Http/Controllers/QuickBooks/{$controllerName}.php");
    }

    /**
     * Ensure directory exists.
     *
     * @param string $directory
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Generate index view content.
     */
    protected function generateIndexView(string $modelName, array $fields, string $viewPath, string $entityViews, string $pluralVariable): string
    {
        $title = Str::plural($modelName);
        $tableHeaders = '';
        $tableRows = '';

        foreach ($fields as $field) {
            $fieldName = Str::snake($field);
            $fieldLabel = Str::title(str_replace('_', ' ', $fieldName));
            $tableHeaders .= "                <th>{$fieldLabel}</th>\n";
            $tableRows .= "                    <td>{{ \${$modelName}->{$fieldName} }}</td>\n";
        }

        return "@extends('layouts.app')

@section('content')
<div class=\"container\">
    <div class=\"row justify-content-center\">
        <div class=\"col-md-12\">
            <div class=\"card\">
                <div class=\"card-header d-flex justify-content-between align-items-center\">
                    <h4>{$title}</h4>
                    <div>
                        <a href=\"{{ route('{$viewPath}.{$entityViews}.create') }}\" class=\"btn btn-primary\">Create New</a>
                        <form method=\"POST\" action=\"{{ route('{$viewPath}.{$entityViews}.sync-all') }}\" class=\"d-inline\">
                            @csrf
                            <button type=\"submit\" class=\"btn btn-info\">Sync All</button>
                        </form>
                    </div>
                </div>

                <div class=\"card-body\">
                    @if(session('success'))
                        <div class=\"alert alert-success\">{{ session('success') }}</div>
                    @endif

                    <div class=\"table-responsive\">
                        <table class=\"table table-striped\">
                            <thead>
                                <tr>
{$tableHeaders}                    <th>QuickBooks ID</th>
                                    <th>Last Synced</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(\${$pluralVariable} as \${$modelName})
                                    <tr>
{$tableRows}                        <td>{{ \${$modelName}->quickbooks_id ?? 'Not synced' }}</td>
                                        <td>{{ \${$modelName}->last_synced_at?->format('M j, Y g:i A') ?? 'Never' }}</td>
                                        <td>
                                            <a href=\"{{ route('{$viewPath}.{$entityViews}.show', \${$modelName}) }}\" class=\"btn btn-sm btn-outline-primary\">View</a>
                                            <a href=\"{{ route('{$viewPath}.{$entityViews}.edit', \${$modelName}) }}\" class=\"btn btn-sm btn-outline-secondary\">Edit</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan=\"" . (count($fields) + 3) . "\" class=\"text-center\">No {$pluralVariable} found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ \${$pluralVariable}->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
";
    }

    /**
     * Generate show view content.
     */
    protected function generateShowView(string $modelName, array $fields, string $viewPath, string $entityViews, string $variableName): string
    {
        $fieldRows = '';
        foreach ($fields as $field) {
            $fieldName = Str::snake($field);
            $fieldLabel = Str::title(str_replace('_', ' ', $fieldName));
            $fieldRows .= "                        <tr>
                            <th>{$fieldLabel}</th>
                            <td>{{ \${$variableName}->{$fieldName} }}</td>
                        </tr>\n";
        }

        return "@extends('layouts.app')

@section('content')
<div class=\"container\">
    <div class=\"row justify-content-center\">
        <div class=\"col-md-8\">
            <div class=\"card\">
                <div class=\"card-header d-flex justify-content-between align-items-center\">
                    <h4>{$modelName} Details</h4>
                    <div>
                        <a href=\"{{ route('{$viewPath}.{$entityViews}.edit', \${$variableName}) }}\" class=\"btn btn-primary\">Edit</a>
                        <a href=\"{{ route('{$viewPath}.{$entityViews}.index') }}\" class=\"btn btn-secondary\">Back to List</a>
                    </div>
                </div>

                <div class=\"card-body\">
                    @if(session('success'))
                        <div class=\"alert alert-success\">{{ session('success') }}</div>
                    @endif

                    <table class=\"table table-bordered\">
{$fieldRows}                        <tr>
                            <th>QuickBooks ID</th>
                            <td>{{ \${$variableName}->quickbooks_id ?? 'Not synced' }}</td>
                        </tr>
                        <tr>
                            <th>Last Synced</th>
                            <td>{{ \${$variableName}->last_synced_at?->format('M j, Y g:i A') ?? 'Never' }}</td>
                        </tr>
                        <tr>
                            <th>Created</th>
                            <td>{{ \${$variableName}->created_at->format('M j, Y g:i A') }}</td>
                        </tr>
                        <tr>
                            <th>Updated</th>
                            <td>{{ \${$variableName}->updated_at->format('M j, Y g:i A') }}</td>
                        </tr>
                    </table>

                    <div class=\"mt-3\">
                        <form method=\"POST\" action=\"{{ route('{$viewPath}.{$entityViews}.sync', \${$variableName}) }}\" class=\"d-inline\">
                            @csrf
                            <button type=\"submit\" class=\"btn btn-info\">Sync with QuickBooks</button>
                        </form>

                        <form method=\"POST\" action=\"{{ route('{$viewPath}.{$entityViews}.destroy', \${$variableName}) }}\" class=\"d-inline\" onsubmit=\"return confirm('Are you sure you want to delete this {$modelName}?')\">
                            @csrf
                            @method('DELETE')
                            <button type=\"submit\" class=\"btn btn-danger\">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
";
    }

    /**
     * Generate create view content.
     */
    protected function generateCreateView(string $modelName, array $fields, string $viewPath, string $entityViews): string
    {
        $formFields = '';
        foreach ($fields as $field) {
            $fieldName = Str::snake($field);
            $fieldLabel = Str::title(str_replace('_', ' ', $fieldName));
            $formFields .= "                    <div class=\"mb-3\">
                        <label for=\"{$fieldName}\" class=\"form-label\">{$fieldLabel}</label>
                        <input type=\"text\" class=\"form-control @error('{$fieldName}') is-invalid @enderror\" 
                               id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{{ old('{$fieldName}') }}\">
                        @error('{$fieldName}')
                            <div class=\"invalid-feedback\">{{ \$message }}</div>
                        @enderror
                    </div>\n";
        }

        return "@extends('layouts.app')

@section('content')
<div class=\"container\">
    <div class=\"row justify-content-center\">
        <div class=\"col-md-8\">
            <div class=\"card\">
                <div class=\"card-header d-flex justify-content-between align-items-center\">
                    <h4>Create New {$modelName}</h4>
                    <a href=\"{{ route('{$viewPath}.{$entityViews}.index') }}\" class=\"btn btn-secondary\">Back to List</a>
                </div>

                <div class=\"card-body\">
                    <form method=\"POST\" action=\"{{ route('{$viewPath}.{$entityViews}.store') }}\">
                        @csrf

{$formFields}
                        <div class=\"d-grid gap-2 d-md-flex justify-content-md-end\">
                            <button type=\"submit\" class=\"btn btn-primary\">Create {$modelName}</button>
                            <a href=\"{{ route('{$viewPath}.{$entityViews}.index') }}\" class=\"btn btn-secondary\">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
";
    }

    /**
     * Generate edit view content.
     */
    protected function generateEditView(string $modelName, array $fields, string $viewPath, string $entityViews, string $variableName): string
    {
        $formFields = '';
        foreach ($fields as $field) {
            $fieldName = Str::snake($field);
            $fieldLabel = Str::title(str_replace('_', ' ', $fieldName));
            $formFields .= "                    <div class=\"mb-3\">
                        <label for=\"{$fieldName}\" class=\"form-label\">{$fieldLabel}</label>
                        <input type=\"text\" class=\"form-control @error('{$fieldName}') is-invalid @enderror\" 
                               id=\"{$fieldName}\" name=\"{$fieldName}\" value=\"{{ old('{$fieldName}', \${$variableName}->{$fieldName}) }}\">
                        @error('{$fieldName}')
                            <div class=\"invalid-feedback\">{{ \$message }}</div>
                        @enderror
                    </div>\n";
        }

        return "@extends('layouts.app')

@section('content')
<div class=\"container\">
    <div class=\"row justify-content-center\">
        <div class=\"col-md-8\">
            <div class=\"card\">
                <div class=\"card-header d-flex justify-content-between align-items-center\">
                    <h4>Edit {$modelName}</h4>
                    <div>
                        <a href=\"{{ route('{$viewPath}.{$entityViews}.show', \${$variableName}) }}\" class=\"btn btn-info\">View</a>
                        <a href=\"{{ route('{$viewPath}.{$entityViews}.index') }}\" class=\"btn btn-secondary\">Back to List</a>
                    </div>
                </div>

                <div class=\"card-body\">
                    <form method=\"POST\" action=\"{{ route('{$viewPath}.{$entityViews}.update', \${$variableName}) }}\">
                        @csrf
                        @method('PUT')

{$formFields}
                        <div class=\"d-grid gap-2 d-md-flex justify-content-md-end\">
                            <button type=\"submit\" class=\"btn btn-primary\">Update {$modelName}</button>
                            <a href=\"{{ route('{$viewPath}.{$entityViews}.show', \${$variableName}) }}\" class=\"btn btn-secondary\">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
";
    }

    /**
     * Display next steps after scaffolding.
     *
     * @param string $modelName
     */
    protected function displayNextSteps(string $modelName): void
    {
        $this->info("\n📋 Next Steps:");
        $this->line("1. Run migrations: php artisan migrate");
        $this->line("2. Add validation rules to the {$modelName}Controller");
        $this->line("3. Customize the generated views as needed");
        $this->line("4. Add authorization policies if required");
        $this->line("5. Test the QuickBooks sync functionality");
        
        $routePrefix = config('quickbooks.scaffolding.route_prefix', 'quickbooks');
        $routeName = Str::kebab(Str::plural($modelName));
        $this->line("\n🔗 Generated routes are available at: /{$routePrefix}/{$routeName}");
    }
}

