<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * QuickBooks Facade
 *
 * @method static \QuickBooksOnline\API\DataService\DataService getDataService()
 * @method static \QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper getOAuth2LoginHelper()
 * @method static string getAuthorizationUrl()
 * @method static array exchangeAuthorizationCode(string $authorizationCode, string $realmId)
 * @method static array refreshAccessToken(\E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken $token)
 * @method static \QuickBooksOnline\API\DataService\DataService getAuthenticatedDataService(\E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken $token)
 * @method static bool isAccessTokenExpired(\E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken $token)
 * @method static bool isRefreshTokenExpired(\E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken $token)
 * @method static bool revokeTokens(\E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken $token)
 * @method static mixed getCompanyInfo(\E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken $token)
 * @method static mixed getConfig(string $key, mixed $default = null)
 *
 * @see \E3DevelopmentSolutions\LaravelQuickBooksIntegration\Services\QuickBooksService
 */
class QuickBooks extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'quickbooks';
    }
}

