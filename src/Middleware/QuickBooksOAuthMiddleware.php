<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Services\QuickBooksService;
use QuickBooksOnline\API\Exception\ServiceException;

class QuickBooksOAuthMiddleware
{
    /**
     * The QuickBooks service instance.
     *
     * @var QuickBooksService
     */
    protected $quickBooksService;

    /**
     * Create a new middleware instance.
     *
     * @param QuickBooksService $quickBooksService
     */
    public function __construct(QuickBooksService $quickBooksService)
    {
        $this->quickBooksService = $quickBooksService;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $scope
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $scope = null): mixed
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return $this->redirectToLogin($request);
        }

        $user = Auth::user();

        // Skip middleware for excluded routes
        if ($this->shouldSkipMiddleware($request)) {
            return $next($request);
        }

        try {
            // Get user's QuickBooks token
            $token = $this->getUserQuickBooksToken($user->id, $scope);

            // If no token exists, redirect to QuickBooks connection
            if (!$token) {
                return $this->redirectToQuickBooksConnection($request, 'no_token');
            }

            // If token is not active, redirect to connection
            if (!$token->is_active) {
                return $this->redirectToQuickBooksConnection($request, 'token_inactive');
            }

            // If refresh token is expired, redirect to new connection
            if ($token->isRefreshTokenExpired()) {
                $token->deactivate();
                return $this->redirectToQuickBooksConnection($request, 'refresh_token_expired');
            }

            // If access token is expired, try to refresh it
            if ($token->isAccessTokenExpired()) {
                $refreshResult = $this->refreshAccessToken($token);
                
                if (!$refreshResult) {
                    return $this->redirectToQuickBooksConnection($request, 'refresh_failed');
                }
                
                $token = $refreshResult;
            }

            // Add token to request for use in controllers
            $request->attributes->set('quickbooks_token', $token);
            $request->attributes->set('quickbooks_realm_id', $token->realm_id);

            return $next($request);

        } catch (\Exception $e) {
            $this->logError('QuickBooks OAuth middleware error', $e);
            
            if ($this->isAjaxRequest($request)) {
                return response()->json([
                    'error' => 'QuickBooks authentication required',
                    'redirect_url' => route(config('quickbooks.middleware.redirect_route', 'quickbooks.connect')),
                ], 401);
            }

            return $this->redirectToQuickBooksConnection($request, 'middleware_error');
        }
    }

    /**
     * Get user's QuickBooks token.
     *
     * @param int $userId
     * @param string|null $scope
     * @return QuickBooksToken|null
     */
    protected function getUserQuickBooksToken(int $userId, ?string $scope = null): ?QuickBooksToken
    {
        $query = QuickBooksToken::where('user_id', $userId)->active();

        // If scope is specified, you could filter by scope here
        // For now, we'll get the most recent active token
        return $query->latest()->first();
    }

    /**
     * Refresh the access token.
     *
     * @param QuickBooksToken $token
     * @return QuickBooksToken|null
     */
    protected function refreshAccessToken(QuickBooksToken $token): ?QuickBooksToken
    {
        try {
            if (!config('quickbooks.tokens.auto_refresh', true)) {
                return null;
            }

            $refreshedTokenData = $this->quickBooksService->refreshAccessToken($token);
            
            if ($token->updateTokens($refreshedTokenData)) {
                $token->refresh();
                return $token;
            }

            return null;

        } catch (ServiceException $e) {
            $this->logError('Failed to refresh QuickBooks access token', $e);
            
            // If refresh fails, deactivate the token
            $token->deactivate();
            
            return null;
        }
    }

    /**
     * Check if the middleware should be skipped for this request.
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldSkipMiddleware(Request $request): bool
    {
        $excludedRoutes = config('quickbooks.middleware.exclude_routes', []);
        $currentRoute = $request->route()?->getName();

        if ($currentRoute && in_array($currentRoute, $excludedRoutes)) {
            return true;
        }

        // Check if current path matches any excluded patterns
        $currentPath = $request->path();
        foreach ($excludedRoutes as $pattern) {
            if (str_contains($pattern, '*')) {
                $pattern = str_replace('*', '.*', $pattern);
                if (preg_match("/^{$pattern}$/", $currentPath)) {
                    return true;
                }
            } elseif ($currentPath === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redirect to QuickBooks connection page.
     *
     * @param Request $request
     * @param string $reason
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function redirectToQuickBooksConnection(Request $request, string $reason = 'unknown')
    {
        $redirectRoute = config('quickbooks.middleware.redirect_route', 'quickbooks.connect');
        $errorMessages = config('quickbooks.errors.messages', []);

        // Store the intended URL for redirect after successful connection
        session(['quickbooks_intended_url' => $request->fullUrl()]);

        // Set appropriate error message
        $message = match ($reason) {
            'no_token' => $errorMessages['unauthorized'] ?? 'QuickBooks connection required.',
            'token_inactive' => $errorMessages['token_invalid'] ?? 'QuickBooks token is inactive.',
            'refresh_token_expired' => $errorMessages['token_expired'] ?? 'QuickBooks connection has expired.',
            'refresh_failed' => $errorMessages['token_expired'] ?? 'Failed to refresh QuickBooks token.',
            'middleware_error' => $errorMessages['connection_failed'] ?? 'QuickBooks authentication error.',
            default => $errorMessages['unauthorized'] ?? 'QuickBooks authentication required.',
        };

        if ($this->isAjaxRequest($request)) {
            return response()->json([
                'error' => $message,
                'reason' => $reason,
                'redirect_url' => route($redirectRoute),
            ], 401);
        }

        return Redirect::route($redirectRoute)
            ->with('error', $message)
            ->with('quickbooks_error_reason', $reason);
    }

    /**
     * Redirect to login page.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function redirectToLogin(Request $request)
    {
        if ($this->isAjaxRequest($request)) {
            return response()->json([
                'error' => 'Authentication required',
                'redirect_url' => route('login'),
            ], 401);
        }

        return Redirect::guest(route('login'));
    }

    /**
     * Check if the request is an AJAX request.
     *
     * @param Request $request
     * @return bool
     */
    protected function isAjaxRequest(Request $request): bool
    {
        return $request->ajax() || 
               $request->wantsJson() || 
               $request->expectsJson() ||
               $request->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Log error messages.
     *
     * @param string $message
     * @param \Exception $exception
     */
    protected function logError(string $message, \Exception $exception): void
    {
        if (config('quickbooks.errors.log_errors', true)) {
            Log::error($message, [
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => config('quickbooks.errors.show_detailed_errors', false) 
                    ? $exception->getTraceAsString() 
                    : null,
            ]);
        }
    }
}

