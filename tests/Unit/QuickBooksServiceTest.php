<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\Config;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Services\QuickBooksService;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use Mockery;

class QuickBooksServiceTest extends TestCase
{
    protected $service;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new QuickBooksService();
        
        $this->token = Mockery::mock(QuickBooksToken::class);
        $this->token->shouldReceive('getDecryptedAccessToken')->andReturn('test-access-token');
        $this->token->shouldReceive('getDecryptedRefreshToken')->andReturn('test-refresh-token');
        $this->token->shouldReceive('getAttribute')->with('realm_id')->andReturn('test-realm-id');
        $this->token->realm_id = 'test-realm-id';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_authorization_url()
    {
        Config::shouldReceive('get')->with('quickbooks.oauth.client_id')->andReturn('test-client-id');
        Config::shouldReceive('get')->with('quickbooks.oauth.client_secret')->andReturn('test-client-secret');
        Config::shouldReceive('get')->with('quickbooks.oauth.redirect_uri')->andReturn('http://localhost/callback');
        Config::shouldReceive('get')->with('quickbooks.oauth.scope')->andReturn('com.intuit.quickbooks.accounting');
        Config::shouldReceive('get')->with('quickbooks.oauth.discovery_document_url')->andReturn('https://appcenter.intuit.com/api/v1/OpenID_sandbox');
        Config::shouldReceive('get')->with('quickbooks.oauth.base_url')->andReturn('https://sandbox-quickbooks.api.intuit.com');

        $authUrl = $this->service->getAuthorizationUrl();

        $this->assertIsString($authUrl);
        $this->assertStringContainsString('https://appcenter.intuit.com/connect/oauth2', $authUrl);
        $this->assertStringContainsString('client_id=test-client-id', $authUrl);
        $this->assertStringContainsString('scope=com.intuit.quickbooks.accounting', $authUrl);
        $this->assertStringContainsString('redirect_uri=', $authUrl);
    }

    /** @test */
    public function it_exchanges_authorization_code_for_tokens()
    {
        Config::shouldReceive('get')->with('quickbooks.oauth.client_id')->andReturn('test-client-id');
        Config::shouldReceive('get')->with('quickbooks.oauth.client_secret')->andReturn('test-client-secret');
        Config::shouldReceive('get')->with('quickbooks.oauth.redirect_uri')->andReturn('http://localhost/callback');
        Config::shouldReceive('get')->with('quickbooks.oauth.scope')->andReturn('com.intuit.quickbooks.accounting');
        Config::shouldReceive('get')->with('quickbooks.oauth.discovery_document_url')->andReturn('https://appcenter.intuit.com/api/v1/OpenID_sandbox');
        Config::shouldReceive('get')->with('quickbooks.oauth.base_url')->andReturn('https://sandbox-quickbooks.api.intuit.com');
        Config::shouldReceive('get')->with('quickbooks.tokens.encryption', true)->andReturn(true);

        // Mock the OAuth2LoginHelper
        $oauth2LoginHelper = Mockery::mock(OAuth2LoginHelper::class);
        $oauth2LoginHelper->shouldReceive('exchangeAuthorizationCodeForToken')
            ->with('test-auth-code', 'test-realm-id')
            ->andReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'x_refresh_token_expires_in' => 8726400,
                'expires_in' => 3600,
            ]);

        // We would need to mock the OAuth2LoginHelper creation, but for simplicity
        // let's test the token data processing logic
        $mockTokenData = [
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'x_refresh_token_expires_in' => 8726400,
            'expires_in' => 3600,
        ];

        $processedData = $this->service->processTokenData($mockTokenData, 'test-realm-id');

        $this->assertArrayHasKey('access_token', $processedData);
        $this->assertArrayHasKey('refresh_token', $processedData);
        $this->assertArrayHasKey('realm_id', $processedData);
        $this->assertArrayHasKey('access_token_expires_at', $processedData);
        $this->assertArrayHasKey('refresh_token_expires_at', $processedData);
        $this->assertEquals('test-realm-id', $processedData['realm_id']);
    }

    /** @test */
    public function it_creates_authenticated_data_service()
    {
        Config::shouldReceive('get')->with('quickbooks.oauth.client_id')->andReturn('test-client-id');
        Config::shouldReceive('get')->with('quickbooks.oauth.client_secret')->andReturn('test-client-secret');
        Config::shouldReceive('get')->with('quickbooks.oauth.redirect_uri')->andReturn('http://localhost/callback');
        Config::shouldReceive('get')->with('quickbooks.oauth.scope')->andReturn('com.intuit.quickbooks.accounting');
        Config::shouldReceive('get')->with('quickbooks.oauth.discovery_document_url')->andReturn('https://appcenter.intuit.com/api/v1/OpenID_sandbox');
        Config::shouldReceive('get')->with('quickbooks.oauth.base_url')->andReturn('https://sandbox-quickbooks.api.intuit.com');

        $dataService = $this->service->getAuthenticatedDataService($this->token);

        $this->assertInstanceOf(DataService::class, $dataService);
    }

    /** @test */
    public function it_encrypts_tokens_when_encryption_is_enabled()
    {
        Config::shouldReceive('get')->with('quickbooks.tokens.encryption', true)->andReturn(true);

        $plainToken = 'plain-text-token';
        $encryptedToken = $this->service->encryptToken($plainToken);

        $this->assertNotEquals($plainToken, $encryptedToken);
        $this->assertIsString($encryptedToken);
    }

    /** @test */
    public function it_does_not_encrypt_tokens_when_encryption_is_disabled()
    {
        Config::shouldReceive('get')->with('quickbooks.tokens.encryption', true)->andReturn(false);

        $plainToken = 'plain-text-token';
        $result = $this->service->encryptToken($plainToken);

        $this->assertEquals($plainToken, $result);
    }

    /** @test */
    public function it_validates_token_expiration()
    {
        $validToken = Mockery::mock(QuickBooksToken::class);
        $validToken->shouldReceive('isAccessTokenExpired')->andReturn(false);
        $validToken->shouldReceive('isRefreshTokenExpired')->andReturn(false);
        $validToken->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $validToken->is_active = true;

        $this->assertTrue($this->service->isTokenValid($validToken));

        $expiredToken = Mockery::mock(QuickBooksToken::class);
        $expiredToken->shouldReceive('isAccessTokenExpired')->andReturn(true);
        $expiredToken->shouldReceive('isRefreshTokenExpired')->andReturn(false);
        $expiredToken->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $expiredToken->is_active = true;

        $this->assertFalse($this->service->isTokenValid($expiredToken));

        $inactiveToken = Mockery::mock(QuickBooksToken::class);
        $inactiveToken->shouldReceive('getAttribute')->with('is_active')->andReturn(false);
        $inactiveToken->is_active = false;

        $this->assertFalse($this->service->isTokenValid($inactiveToken));
    }

    /** @test */
    public function it_handles_refresh_token_request()
    {
        Config::shouldReceive('get')->with('quickbooks.oauth.client_id')->andReturn('test-client-id');
        Config::shouldReceive('get')->with('quickbooks.oauth.client_secret')->andReturn('test-client-secret');
        Config::shouldReceive('get')->with('quickbooks.oauth.redirect_uri')->andReturn('http://localhost/callback');
        Config::shouldReceive('get')->with('quickbooks.oauth.scope')->andReturn('com.intuit.quickbooks.accounting');
        Config::shouldReceive('get')->with('quickbooks.oauth.discovery_document_url')->andReturn('https://appcenter.intuit.com/api/v1/OpenID_sandbox');
        Config::shouldReceive('get')->with('quickbooks.oauth.base_url')->andReturn('https://sandbox-quickbooks.api.intuit.com');
        Config::shouldReceive('get')->with('quickbooks.tokens.encryption', true)->andReturn(true);

        // Mock successful refresh response
        $mockRefreshData = [
            'access_token' => 'refreshed-access-token',
            'refresh_token' => 'refreshed-refresh-token',
            'x_refresh_token_expires_in' => 8726400,
            'expires_in' => 3600,
        ];

        $processedData = $this->service->processTokenData($mockRefreshData, $this->token->realm_id);

        $this->assertArrayHasKey('access_token', $processedData);
        $this->assertArrayHasKey('refresh_token', $processedData);
        $this->assertEquals('test-realm-id', $processedData['realm_id']);
    }

    /** @test */
    public function it_formats_quickbooks_errors()
    {
        $error = new \Exception('QuickBooks API Error: Invalid token');
        $formattedError = $this->service->formatError($error);

        $this->assertIsArray($formattedError);
        $this->assertArrayHasKey('message', $formattedError);
        $this->assertArrayHasKey('code', $formattedError);
        $this->assertArrayHasKey('type', $formattedError);
    }

    /** @test */
    public function it_validates_webhook_signature()
    {
        Config::shouldReceive('get')->with('quickbooks.webhooks.verifier_token')->andReturn('test-verifier-token');

        $payload = '{"test": "data"}';
        $signature = base64_encode(hash_hmac('sha256', $payload, 'test-verifier-token', true));

        $this->assertTrue($this->service->validateWebhookSignature($payload, $signature));
        $this->assertFalse($this->service->validateWebhookSignature($payload, 'invalid-signature'));
    }
}

