<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Commands\ScaffoldQuickBooksModel;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Mockery;

class ScaffoldQuickBooksModelTest extends TestCase
{
    protected $command;
    protected $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->command = new ScaffoldQuickBooksModel();
        
        $application = new Application();
        $application->add($this->command);
        
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_fails_with_invalid_quickbooks_entity()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => ['fields' => ['name', 'email']],
            'Invoice' => ['fields' => ['number', 'amount']],
        ]);

        $this->commandTester->execute([
            'model' => 'InvalidEntity'
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('is not a supported QuickBooks entity', $output);
        $this->assertEquals(ScaffoldQuickBooksModel::FAILURE, $this->commandTester->getStatusCode());
    }

    /** @test */
    public function it_shows_supported_entities_on_invalid_input()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => ['fields' => ['name', 'email']],
            'Invoice' => ['fields' => ['number', 'amount']],
        ]);

        $this->commandTester->execute([
            'model' => 'InvalidEntity'
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Supported entities: Customer, Invoice', $output);
    }

    /** @test */
    public function it_generates_migration_file()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => [
                'fields' => ['name', 'email', 'phone'],
                'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer'
            ],
        ]);

        File::shouldReceive('exists')->andReturn(false);
        File::shouldReceive('put')->once()->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('append')->andReturn(true);

        $this->commandTester->execute([
            'model' => 'Customer'
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Created migration:', $output);
    }

    /** @test */
    public function it_generates_model_file()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => [
                'fields' => ['name', 'email', 'phone'],
                'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer'
            ],
        ]);
        Config::shouldReceive('get')->with('quickbooks.scaffolding.model_namespace', 'App\\Models\\QuickBooks')
            ->andReturn('App\\Models\\QuickBooks');

        File::shouldReceive('exists')->andReturn(false);
        File::shouldReceive('put')->once()->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('append')->andReturn(true);

        $this->commandTester->execute([
            'model' => 'Customer'
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Created model:', $output);
        $this->assertStringContainsString('App\\Models\\QuickBooks\\Customer', $output);
    }

    /** @test */
    public function it_generates_controller_file()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => [
                'fields' => ['name', 'email', 'phone'],
                'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer'
            ],
        ]);
        Config::shouldReceive('get')->with('quickbooks.scaffolding.model_namespace', 'App\\Models\\QuickBooks')
            ->andReturn('App\\Models\\QuickBooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.controller_namespace', 'App\\Http\\Controllers\\QuickBooks')
            ->andReturn('App\\Http\\Controllers\\QuickBooks');

        File::shouldReceive('exists')->andReturn(false);
        File::shouldReceive('put')->times(3)->andReturn(true); // migration, model, controller
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('append')->andReturn(true);

        $this->commandTester->execute([
            'model' => 'Customer'
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Created controller:', $output);
        $this->assertStringContainsString('App\\Http\\Controllers\\QuickBooks\\CustomerController', $output);
    }

    /** @test */
    public function it_generates_view_files()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => [
                'fields' => ['name', 'email', 'phone'],
                'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer'
            ],
        ]);
        Config::shouldReceive('get')->with('quickbooks.scaffolding.model_namespace', 'App\\Models\\QuickBooks')
            ->andReturn('App\\Models\\QuickBooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.controller_namespace', 'App\\Http\\Controllers\\QuickBooks')
            ->andReturn('App\\Http\\Controllers\\QuickBooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.view_path', 'quickbooks')
            ->andReturn('quickbooks');

        File::shouldReceive('exists')->andReturn(false);
        File::shouldReceive('put')->times(7)->andReturn(true); // migration, model, controller, 4 views
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('append')->andReturn(true);

        $this->commandTester->execute([
            'model' => 'Customer'
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Created view: quickbooks/customers/index.blade.php', $output);
        $this->assertStringContainsString('Created view: quickbooks/customers/show.blade.php', $output);
        $this->assertStringContainsString('Created view: quickbooks/customers/create.blade.php', $output);
        $this->assertStringContainsString('Created view: quickbooks/customers/edit.blade.php', $output);
    }

    /** @test */
    public function it_generates_routes()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => [
                'fields' => ['name', 'email', 'phone'],
                'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer'
            ],
        ]);
        Config::shouldReceive('get')->with('quickbooks.scaffolding.model_namespace', 'App\\Models\\QuickBooks')
            ->andReturn('App\\Models\\QuickBooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.controller_namespace', 'App\\Http\\Controllers\\QuickBooks')
            ->andReturn('App\\Http\\Controllers\\QuickBooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.view_path', 'quickbooks')
            ->andReturn('quickbooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.route_prefix', 'quickbooks')
            ->andReturn('quickbooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.route_middleware', ['web', 'auth', 'quickbooks.oauth'])
            ->andReturn(['web', 'auth', 'quickbooks.oauth']);

        File::shouldReceive('exists')->andReturn(false);
        File::shouldReceive('put')->times(7)->andReturn(true); // migration, model, controller, 4 views
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('append')->once()->andReturn(true); // routes
        File::shouldReceive('get')->with(base_path('routes/web.php'))->andReturn('<?php // existing routes');

        $this->commandTester->execute([
            'model' => 'Customer'
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Added routes for Customer', $output);
    }

    /** @test */
    public function it_skips_generation_with_no_options()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => [
                'fields' => ['name', 'email', 'phone'],
                'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer'
            ],
        ]);

        File::shouldReceive('exists')->andReturn(false);
        File::shouldReceive('put')->once()->andReturn(true); // only model
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);

        $this->commandTester->execute([
            'model' => 'Customer',
            '--no-migration' => true,
            '--no-controller' => true,
            '--no-views' => true,
            '--no-routes' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringNotContainsString('Created migration:', $output);
        $this->assertStringNotContainsString('Created controller:', $output);
        $this->assertStringNotContainsString('Created view:', $output);
        $this->assertStringNotContainsString('Added routes:', $output);
    }

    /** @test */
    public function it_shows_next_steps_after_successful_scaffolding()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => [
                'fields' => ['name', 'email', 'phone'],
                'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer'
            ],
        ]);
        Config::shouldReceive('get')->with('quickbooks.scaffolding.model_namespace', 'App\\Models\\QuickBooks')
            ->andReturn('App\\Models\\QuickBooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.controller_namespace', 'App\\Http\\Controllers\\QuickBooks')
            ->andReturn('App\\Http\\Controllers\\QuickBooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.view_path', 'quickbooks')
            ->andReturn('quickbooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.route_prefix', 'quickbooks')
            ->andReturn('quickbooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.route_middleware', ['web', 'auth', 'quickbooks.oauth'])
            ->andReturn(['web', 'auth', 'quickbooks.oauth']);

        File::shouldReceive('exists')->andReturn(false);
        File::shouldReceive('put')->times(7)->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('append')->andReturn(true);
        File::shouldReceive('get')->andReturn('<?php // existing routes');

        $this->commandTester->execute([
            'model' => 'Customer'
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Next Steps:', $output);
        $this->assertStringContainsString('Run migrations: php artisan migrate', $output);
        $this->assertStringContainsString('Generated routes are available at: /quickbooks/customers', $output);
        $this->assertEquals(ScaffoldQuickBooksModel::SUCCESS, $this->commandTester->getStatusCode());
    }

    /** @test */
    public function it_warns_about_existing_files_without_force_option()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => [
                'fields' => ['name', 'email', 'phone'],
                'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer'
            ],
        ]);

        File::shouldReceive('exists')->andReturn(true); // Files already exist
        File::shouldReceive('isDirectory')->andReturn(true);

        $this->commandTester->execute([
            'model' => 'Customer'
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('already exists', $output);
    }

    /** @test */
    public function it_overwrites_existing_files_with_force_option()
    {
        Config::shouldReceive('get')->with('quickbooks.entities', [])->andReturn([
            'Customer' => [
                'fields' => ['name', 'email', 'phone'],
                'class' => 'QuickBooksOnline\\API\\Data\\IPPCustomer'
            ],
        ]);
        Config::shouldReceive('get')->with('quickbooks.scaffolding.model_namespace', 'App\\Models\\QuickBooks')
            ->andReturn('App\\Models\\QuickBooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.controller_namespace', 'App\\Http\\Controllers\\QuickBooks')
            ->andReturn('App\\Http\\Controllers\\QuickBooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.view_path', 'quickbooks')
            ->andReturn('quickbooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.route_prefix', 'quickbooks')
            ->andReturn('quickbooks');
        Config::shouldReceive('get')->with('quickbooks.scaffolding.route_middleware', ['web', 'auth', 'quickbooks.oauth'])
            ->andReturn(['web', 'auth', 'quickbooks.oauth']);

        File::shouldReceive('exists')->andReturn(true); // Files already exist
        File::shouldReceive('put')->times(7)->andReturn(true); // Should overwrite
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('append')->andReturn(true);
        File::shouldReceive('get')->andReturn('<?php // existing routes');

        $this->commandTester->execute([
            'model' => 'Customer',
            '--force' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringNotContainsString('already exists', $output);
        $this->assertStringContainsString('Successfully scaffolded', $output);
    }
}

