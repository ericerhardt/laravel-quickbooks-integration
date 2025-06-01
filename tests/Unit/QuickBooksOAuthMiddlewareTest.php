<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Middleware\QuickBooksOAuthMiddleware;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Services\QuickBooksService;
use Mockery;

class QuickBooksOAuthMiddlewareTest extends TestCase
{
    protected $middleware;
    protected $quickBooksService;
    protected $request;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->quickBooksService = Mockery::mock(QuickBooksService::class);
        $this->middleware = new QuickBooksOAuthMiddleware($this->quickBooksService);
        $this->request = Request::create('/test', 'GET');
        
        $this->user = Mockery::mock();
        $this->user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $this->user->id = 1;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_redirects_unauthenticated_users_to_login()
    {
        Auth::shouldReceive('check')->andReturn(false);
        
        $response = $this->middleware->handle($this->request, function () {
            return new Response('Success');
        });

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /** @test */
    public function it_redirects_when_no_quickbooks_token_exists()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($this->user);
        
        QuickBooksToken::shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        QuickBooksToken::shouldReceive('active')->andReturnSelf();
        QuickBooksToken::shouldReceive('latest')->andReturnSelf();
        QuickBooksToken::shouldReceive('first')->andReturn(null);

        Config::shouldReceive('get')->with('quickbooks.middleware.redirect_route', 'quickbooks.connect')
            ->andReturn('quickbooks.connect');

        $response = $this->middleware->handle($this->request, function () {
            return new Response('Success');
        });

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /** @test */
    public function it_redirects_when_token_is_inactive()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($this->user);
        
        $token = Mockery::mock(QuickBooksToken::class);
        $token->shouldReceive('getAttribute')->with('is_active')->andReturn(false);
        $token->is_active = false;

        QuickBooksToken::shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        QuickBooksToken::shouldReceive('active')->andReturnSelf();
        QuickBooksToken::shouldReceive('latest')->andReturnSelf();
        QuickBooksToken::shouldReceive('first')->andReturn($token);

        Config::shouldReceive('get')->with('quickbooks.middleware.redirect_route', 'quickbooks.connect')
            ->andReturn('quickbooks.connect');

        $response = $this->middleware->handle($this->request, function () {
            return new Response('Success');
        });

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /** @test */
    public function it_refreshes_expired_access_token()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($this->user);
        
        $token = Mockery::mock(QuickBooksToken::class);
        $token->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $token->shouldReceive('isRefreshTokenExpired')->andReturn(false);
        $token->shouldReceive('isAccessTokenExpired')->andReturn(true);
        $token->shouldReceive('refresh')->andReturnSelf();
        $token->is_active = true;
        $token->realm_id = 'test-realm';

        QuickBooksToken::shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        QuickBooksToken::shouldReceive('active')->andReturnSelf();
        QuickBooksToken::shouldReceive('latest')->andReturnSelf();
        QuickBooksToken::shouldReceive('first')->andReturn($token);

        $refreshedTokenData = [
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'access_token_expires_at' => now()->addHour(),
            'refresh_token_expires_at' => now()->addDays(100),
        ];

        $this->quickBooksService->shouldReceive('refreshAccessToken')
            ->with($token)
            ->andReturn($refreshedTokenData);

        $token->shouldReceive('updateTokens')->with($refreshedTokenData)->andReturn(true);

        Config::shouldReceive('get')->with('quickbooks.tokens.auto_refresh', true)->andReturn(true);

        $response = $this->middleware->handle($this->request, function ($request) {
            $this->assertNotNull($request->attributes->get('quickbooks_token'));
            $this->assertEquals('test-realm', $request->attributes->get('quickbooks_realm_id'));
            return new Response('Success');
        });

        $this->assertEquals('Success', $response->getContent());
    }

    /** @test */
    public function it_passes_through_with_valid_token()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($this->user);
        
        $token = Mockery::mock(QuickBooksToken::class);
        $token->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $token->shouldReceive('isRefreshTokenExpired')->andReturn(false);
        $token->shouldReceive('isAccessTokenExpired')->andReturn(false);
        $token->is_active = true;
        $token->realm_id = 'test-realm';

        QuickBooksToken::shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        QuickBooksToken::shouldReceive('active')->andReturnSelf();
        QuickBooksToken::shouldReceive('latest')->andReturnSelf();
        QuickBooksToken::shouldReceive('first')->andReturn($token);

        $response = $this->middleware->handle($this->request, function ($request) {
            $this->assertNotNull($request->attributes->get('quickbooks_token'));
            $this->assertEquals('test-realm', $request->attributes->get('quickbooks_realm_id'));
            return new Response('Success');
        });

        $this->assertEquals('Success', $response->getContent());
    }

    /** @test */
    public function it_returns_json_response_for_ajax_requests()
    {
        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($this->user);
        
        QuickBooksToken::shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        QuickBooksToken::shouldReceive('active')->andReturnSelf();
        QuickBooksToken::shouldReceive('latest')->andReturnSelf();
        QuickBooksToken::shouldReceive('first')->andReturn(null);

        Config::shouldReceive('get')->with('quickbooks.middleware.redirect_route', 'quickbooks.connect')
            ->andReturn('quickbooks.connect');
        Config::shouldReceive('get')->with('quickbooks.errors.messages', [])->andReturn([]);

        $response = $this->middleware->handle($this->request, function () {
            return new Response('Success');
        });

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function it_skips_middleware_for_excluded_routes()
    {
        $this->request = Request::create('/quickbooks/connect', 'GET');
        $this->request->setRouteResolver(function () {
            $route = Mockery::mock();
            $route->shouldReceive('getName')->andReturn('quickbooks.connect');
            return $route;
        });

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($this->user);

        Config::shouldReceive('get')->with('quickbooks.middleware.exclude_routes', [])
            ->andReturn(['quickbooks.connect']);

        $response = $this->middleware->handle($this->request, function () {
            return new Response('Success');
        });

        $this->assertEquals('Success', $response->getContent());
    }

    /** @test */
    public function it_handles_refresh_token_failure()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($this->user);
        
        $token = Mockery::mock(QuickBooksToken::class);
        $token->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $token->shouldReceive('isRefreshTokenExpired')->andReturn(false);
        $token->shouldReceive('isAccessTokenExpired')->andReturn(true);
        $token->shouldReceive('deactivate')->andReturn(true);
        $token->is_active = true;

        QuickBooksToken::shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        QuickBooksToken::shouldReceive('active')->andReturnSelf();
        QuickBooksToken::shouldReceive('latest')->andReturnSelf();
        QuickBooksToken::shouldReceive('first')->andReturn($token);

        $this->quickBooksService->shouldReceive('refreshAccessToken')
            ->with($token)
            ->andThrow(new \Exception('Refresh failed'));

        Config::shouldReceive('get')->with('quickbooks.tokens.auto_refresh', true)->andReturn(true);
        Config::shouldReceive('get')->with('quickbooks.middleware.redirect_route', 'quickbooks.connect')
            ->andReturn('quickbooks.connect');

        $response = $this->middleware->handle($this->request, function () {
            return new Response('Success');
        });

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }
}

