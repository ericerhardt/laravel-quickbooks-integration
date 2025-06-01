<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Services;

use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\Exception\ServiceException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken;

class QuickBooksService
{
    /**
     * Package configuration
     *
     * @var array
     */
    protected $config;

    /**
     * QuickBooks DataService instance
     *
     * @var DataService|null
     */
    protected $dataService;

    /**
     * OAuth2LoginHelper instance
     *
     * @var OAuth2LoginHelper|null
     */
    protected $oAuth2LoginHelper;

    /**
     * Current user's QuickBooks token
     *
     * @var QuickBooksToken|null
     */
    protected $token;

    /**
     * Create a new QuickBooksService instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Initialize DataService with OAuth configuration
     *
     * @return DataService
     */
    public function getDataService(): DataService
    {
        if ($this->dataService === null) {
            $this->dataService = DataService::Configure([
                'auth_mode' => 'oauth2',
                'ClientID' => $this->config['oauth']['client_id'],
                'ClientSecret' => $this->config['oauth']['client_secret'],
                'RedirectURI' => $this->config['oauth']['redirect_uri'],
                'scope' => $this->config['oauth']['scope'],
                'baseUrl' => $this->config['oauth']['base_url'],
            ]);

            // Set minor version if specified
            if (isset($this->config['api']['minor_version'])) {
                $this->dataService->setMinorVersion($this->config['api']['minor_version']);
            }

            // Enable logging if configured
            if ($this->config['api']['log_requests']) {
                $this->dataService->enableLog();
            }
        }

        return $this->dataService;
    }

    /**
     * Get OAuth2LoginHelper instance
     *
     * @return OAuth2LoginHelper
     */
    public function getOAuth2LoginHelper(): OAuth2LoginHelper
    {
        if ($this->oAuth2LoginHelper === null) {
            $this->oAuth2LoginHelper = $this->getDataService()->getOAuth2LoginHelper();
        }

        return $this->oAuth2LoginHelper;
    }

    /**
     * Generate authorization URL for OAuth flow
     *
     * @return string
     */
    public function getAuthorizationUrl(): string
    {
        return $this->getOAuth2LoginHelper()->getAuthorizationCodeURL();
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $authorizationCode
     * @param string $realmId
     * @return array
     * @throws ServiceException
     */
    public function exchangeAuthorizationCode(string $authorizationCode, string $realmId): array
    {
        try {
            $accessTokenObj = $this->getOAuth2LoginHelper()->exchangeAuthorizationCodeForToken(
                $authorizationCode,
                $realmId
            );

            $accessToken = $accessTokenObj->getAccessToken();
            $refreshToken = $accessTokenObj->getRefreshToken();
            $accessTokenExpiresAt = now()->addSeconds($this->config['tokens']['access_token_lifetime']);
            $refreshTokenExpiresAt = now()->addSeconds($this->config['tokens']['refresh_token_lifetime']);

            return [
                'access_token' => $this->config['tokens']['encryption'] ? Crypt::encryptString($accessToken) : $accessToken,
                'refresh_token' => $this->config['tokens']['encryption'] ? Crypt::encryptString($refreshToken) : $refreshToken,
                'realm_id' => $realmId,
                'access_token_expires_at' => $accessTokenExpiresAt,
                'refresh_token_expires_at' => $refreshTokenExpiresAt,
            ];
        } catch (ServiceException $e) {
            $this->logError('Failed to exchange authorization code', $e);
            throw $e;
        }
    }

    /**
     * Refresh access token using refresh token
     *
     * @param QuickBooksToken $token
     * @return array
     * @throws ServiceException
     */
    public function refreshAccessToken(QuickBooksToken $token): array
    {
        try {
            $refreshToken = $this->config['tokens']['encryption'] 
                ? Crypt::decryptString($token->refresh_token)
                : $token->refresh_token;

            // Configure DataService with existing tokens for refresh
            $dataService = DataService::Configure([
                'auth_mode' => 'oauth2',
                'ClientID' => $this->config['oauth']['client_id'],
                'ClientSecret' => $this->config['oauth']['client_secret'],
                'refreshTokenKey' => $refreshToken,
                'QBORealmID' => $token->realm_id,
                'baseUrl' => $this->config['oauth']['base_url'],
            ]);

            $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
            $accessTokenObj = $OAuth2LoginHelper->refreshToken();

            $newAccessToken = $accessTokenObj->getAccessToken();
            $newRefreshToken = $accessTokenObj->getRefreshToken();
            $accessTokenExpiresAt = now()->addSeconds($this->config['tokens']['access_token_lifetime']);

            return [
                'access_token' => $this->config['tokens']['encryption'] ? Crypt::encryptString($newAccessToken) : $newAccessToken,
                'refresh_token' => $this->config['tokens']['encryption'] ? Crypt::encryptString($newRefreshToken) : $newRefreshToken,
                'access_token_expires_at' => $accessTokenExpiresAt,
            ];
        } catch (ServiceException $e) {
            $this->logError('Failed to refresh access token', $e);
            throw $e;
        }
    }

    /**
     * Configure DataService with user's tokens
     *
     * @param QuickBooksToken $token
     * @return DataService
     */
    public function getAuthenticatedDataService(QuickBooksToken $token): DataService
    {
        $accessToken = $this->config['tokens']['encryption'] 
            ? Crypt::decryptString($token->access_token)
            : $token->access_token;

        $refreshToken = $this->config['tokens']['encryption'] 
            ? Crypt::decryptString($token->refresh_token)
            : $token->refresh_token;

        $dataService = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => $this->config['oauth']['client_id'],
            'ClientSecret' => $this->config['oauth']['client_secret'],
            'accessTokenKey' => $accessToken,
            'refreshTokenKey' => $refreshToken,
            'QBORealmID' => $token->realm_id,
            'baseUrl' => $this->config['oauth']['base_url'],
        ]);

        // Set minor version if specified
        if (isset($this->config['api']['minor_version'])) {
            $dataService->setMinorVersion($this->config['api']['minor_version']);
        }

        // Enable logging if configured
        if ($this->config['api']['log_requests']) {
            $dataService->enableLog();
        }

        return $dataService;
    }

    /**
     * Check if access token is expired
     *
     * @param QuickBooksToken $token
     * @return bool
     */
    public function isAccessTokenExpired(QuickBooksToken $token): bool
    {
        return $token->access_token_expires_at <= now();
    }

    /**
     * Check if refresh token is expired
     *
     * @param QuickBooksToken $token
     * @return bool
     */
    public function isRefreshTokenExpired(QuickBooksToken $token): bool
    {
        return $token->refresh_token_expires_at <= now();
    }

    /**
     * Revoke OAuth tokens
     *
     * @param QuickBooksToken $token
     * @return bool
     */
    public function revokeTokens(QuickBooksToken $token): bool
    {
        try {
            $refreshToken = $this->config['tokens']['encryption'] 
                ? Crypt::decryptString($token->refresh_token)
                : $token->refresh_token;

            $dataService = DataService::Configure([
                'auth_mode' => 'oauth2',
                'ClientID' => $this->config['oauth']['client_id'],
                'ClientSecret' => $this->config['oauth']['client_secret'],
                'refreshTokenKey' => $refreshToken,
                'QBORealmID' => $token->realm_id,
                'baseUrl' => $this->config['oauth']['base_url'],
            ]);

            $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
            $OAuth2LoginHelper->revokeToken($refreshToken);

            return true;
        } catch (ServiceException $e) {
            $this->logError('Failed to revoke tokens', $e);
            return false;
        }
    }

    /**
     * Get company information
     *
     * @param QuickBooksToken $token
     * @return mixed
     */
    public function getCompanyInfo(QuickBooksToken $token)
    {
        try {
            $dataService = $this->getAuthenticatedDataService($token);
            return $dataService->getCompanyInfo();
        } catch (ServiceException $e) {
            $this->logError('Failed to get company info', $e);
            throw $e;
        }
    }

    /**
     * Log error messages
     *
     * @param string $message
     * @param ServiceException $exception
     */
    protected function logError(string $message, ServiceException $exception): void
    {
        if ($this->config['errors']['log_errors']) {
            Log::error($message, [
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }
}

