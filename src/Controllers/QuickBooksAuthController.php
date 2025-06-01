<?php

namespace E3DevelopmentSolutions\LaravelQuickBooksIntegration\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksToken;
use E3DevelopmentSolutions\LaravelQuickBooksIntegration\Models\QuickBooksOAuthState;
use QuickBooksOnline\API\Exception\ServiceException;

class QuickBooksAuthController extends Controller
{
    /**
     * The QuickBooks service instance.
     *
     * @var QuickBooksService
     */
    protected $quickBooksService;

    /**
     * Create a new controller instance.
     *
     * @param QuickBooksService $quickBooksService
     */
    public function __construct(QuickBooksService $quickBooksService)
    {
        $this->quickBooksService = $quickBooksService;
    }

    /**
     * Initiate QuickBooks OAuth connection.
     *
     * @param Request $request
     * @return RedirectResponse|View
     */
    public function connect(Request $request)
    {
        try {
            // Check if user is already connected
            $user = Auth::user();
            $existingToken = QuickBooksToken::where('user_id', $user->id)
                ->active()
                ->valid()
                ->first();

            if ($existingToken) {
                $intendedUrl = session('quickbooks_intended_url', config('quickbooks.middleware.success_route', 'dashboard'));
                session()->forget('quickbooks_intended_url');
                
                return Redirect::to($intendedUrl)
                    ->with('success', 'You are already connected to QuickBooks.');
            }

            // Generate authorization URL
            $authorizationUrl = $this->quickBooksService->getAuthorizationUrl();

            // Create OAuth state record in database
            $oauthState = QuickBooksOAuthState::createForUser($user->id, 60); // 60 minutes expiration

            // Use simple state token
            $stateToken = $oauthState->state_token;

            // Add state parameter to URL if not already present
            if (!str_contains($authorizationUrl, 'state=')) {
                $authorizationUrl .= '&state=' . $stateToken;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'authorization_url' => $authorizationUrl,
                    'message' => 'Redirect to QuickBooks for authorization',
                ]);
            }

            // For web requests, you might want to show a page with a connect button
            // or directly redirect to QuickBooks
            return Redirect::away($authorizationUrl);

        } catch (\Exception $e) {
            $this->logError('Failed to initiate QuickBooks connection', $e);

            $errorMessage = config('quickbooks.errors.show_detailed_errors', false) 
                ? $e->getMessage() 
                : 'Failed to connect to QuickBooks. Please try again.';

            if ($request->expectsJson()) {
                return response()->json(['error' => $errorMessage], 500);
            }

            return Redirect::route(config('quickbooks.middleware.error_route', 'quickbooks.error'))
                ->with('error', $errorMessage);
        }
    }

    /**
     * Handle QuickBooks OAuth callback.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function callback(Request $request)
    {
        try {
            // Validate required parameters
            $authorizationCode = $request->get('code');
            $realmId = $request->get('realmId');
            $state = $request->get('state');
            $error = $request->get('error');

            // Check for OAuth errors
            if ($error) {
                $errorDescription = $request->get('error_description', 'Unknown OAuth error');
                throw new \Exception("OAuth Error: {$error} - {$errorDescription}");
            }

            // Validate required parameters
            if (!$authorizationCode || !$realmId) {
                throw new \Exception('Missing required OAuth parameters (code or realmId)');
            }

            // Find and validate OAuth state from database
            if (!$state) {
                throw new \Exception('Missing state parameter');
            }

            $oauthState = QuickBooksOAuthState::findValidState($state);
            if (!$oauthState) {
                throw new \Exception('Invalid or expired OAuth state');
            }

            $userId = $oauthState->user_id;

            // Consume the OAuth state (delete it after use)
            $oauthState->consume();

            // Clean up any expired states
            QuickBooksOAuthState::cleanup();

            // Get the user model using the configured auth model
            $userModel = config('auth.providers.users.model', \App\Models\User::class);
            $user = $userModel::find($userId);
            if (!$user) {
                throw new \Exception('User not found. Please try connecting again.');
            }

            // Deactivate any existing tokens for this user
            QuickBooksToken::where('user_id', $user->id)->update(['is_active' => false]);

            // Exchange authorization code for tokens
            $tokenData = $this->quickBooksService->exchangeAuthorizationCode($authorizationCode, $realmId);

            // Get company information
            $companyInfo = null;
            try {
                // Create a temporary token object to get company info
                $tempToken = new QuickBooksToken($tokenData + ['user_id' => $user->id]);
                $companyInfo = $this->quickBooksService->getCompanyInfo($tempToken);
            } catch (\Exception $e) {
                // Company info is optional, log but don't fail
                $this->logError('Failed to retrieve company info', $e);
            }

            // Create new token record
            $token = QuickBooksToken::create([
                'user_id' => $user->id,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'realm_id' => $tokenData['realm_id'],
                'access_token_expires_at' => $tokenData['access_token_expires_at'],
                'refresh_token_expires_at' => $tokenData['refresh_token_expires_at'],
                'company_name' => $companyInfo->Name ?? null,
                'company_email' => $companyInfo->Email ?? null,
                'is_active' => true,
            ]);

            // Get intended URL or default success route
            $intendedUrl = session('quickbooks_intended_url', route(config('quickbooks.middleware.success_route', 'dashboard')));
            session()->forget('quickbooks_intended_url');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully connected to QuickBooks',
                    'company_name' => $token->company_name,
                    'realm_id' => $token->realm_id,
                    'redirect_url' => $intendedUrl,
                ]);
            }

            return Redirect::to($intendedUrl)
                ->with('success', 'Successfully connected to QuickBooks' . 
                    ($token->company_name ? " ({$token->company_name})" : '') . '!');

        } catch (ServiceException $e) {
            $this->logError('QuickBooks API error during callback', $e);
            return $this->handleCallbackError($request, 'QuickBooks API error: ' . $e->getMessage());

        } catch (\Exception $e) {
            $this->logError('Error during QuickBooks OAuth callback', $e);
            return $this->handleCallbackError($request, $e->getMessage());
        }
    }

    /**
     * Disconnect from QuickBooks.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function disconnect(Request $request)
    {
        try {
            $user = Auth::user();
            $token = QuickBooksToken::where('user_id', $user->id)->active()->first();

            if ($token) {
                // Attempt to revoke tokens with QuickBooks
                $this->quickBooksService->revokeTokens($token);
                
                // Deactivate local token
                $token->deactivate();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully disconnected from QuickBooks',
                ]);
            }

            return Redirect::back()
                ->with('success', 'Successfully disconnected from QuickBooks.');

        } catch (\Exception $e) {
            $this->logError('Error disconnecting from QuickBooks', $e);

            $errorMessage = config('quickbooks.errors.show_detailed_errors', false) 
                ? $e->getMessage() 
                : 'Error disconnecting from QuickBooks.';

            if ($request->expectsJson()) {
                return response()->json(['error' => $errorMessage], 500);
            }

            return Redirect::back()->with('error', $errorMessage);
        }
    }

    /**
     * Get QuickBooks connection status.
     *
     * @param Request $request
     * @return JsonResponse|View
     */
    public function status(Request $request)
    {
        $user = Auth::user();
        $token = QuickBooksToken::where('user_id', $user->id)->active()->first();

        $status = [
            'connected' => false,
            'company_name' => null,
            'realm_id' => null,
            'access_token_expires_at' => null,
            'refresh_token_expires_at' => null,
            'needs_refresh' => false,
        ];

        if ($token) {
            $status = [
                'connected' => true,
                'company_name' => $token->company_name,
                'realm_id' => $token->realm_id,
                'access_token_expires_at' => $token->access_token_expires_at,
                'refresh_token_expires_at' => $token->refresh_token_expires_at,
                'needs_refresh' => $token->isAccessTokenExpired(),
            ];
        }

        if ($request->expectsJson()) {
            return response()->json($status);
        }

        return view('quickbooks::status', compact('status', 'token'));
    }

    /**
     * Refresh access token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $token = QuickBooksToken::where('user_id', $user->id)->active()->first();

            if (!$token) {
                return response()->json(['error' => 'No active QuickBooks token found'], 404);
            }

            if (!$token->canBeRefreshed()) {
                return response()->json(['error' => 'Token cannot be refreshed'], 400);
            }

            $refreshedTokenData = $this->quickBooksService->refreshAccessToken($token);
            $token->updateTokens($refreshedTokenData);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'access_token_expires_at' => $token->access_token_expires_at,
            ]);

        } catch (ServiceException $e) {
            $this->logError('Failed to refresh QuickBooks token', $e);
            return response()->json(['error' => 'Failed to refresh token: ' . $e->getMessage()], 500);

        } catch (\Exception $e) {
            $this->logError('Error refreshing QuickBooks token', $e);
            return response()->json(['error' => 'Error refreshing token'], 500);
        }
    }

    /**
     * Show error page.
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function error(Request $request)
    {
        $error = session('error', 'An unknown error occurred');
        $reason = session('quickbooks_error_reason', 'unknown');

        if ($request->expectsJson()) {
            return response()->json([
                'error' => $error,
                'reason' => $reason,
            ], 400);
        }

        return view('quickbooks::error', compact('error', 'reason'));
    }

    /**
     * Handle callback errors.
     *
     * @param Request $request
     * @param string $errorMessage
     * @return RedirectResponse|JsonResponse
     */
    protected function handleCallbackError(Request $request, string $errorMessage)
    {
        $userFriendlyMessage = config('quickbooks.errors.show_detailed_errors', false) 
            ? $errorMessage 
            : 'Failed to connect to QuickBooks. Please try again.';

        if ($request->expectsJson()) {
            return response()->json(['error' => $userFriendlyMessage], 400);
        }

        return Redirect::route(config('quickbooks.middleware.error_route', 'quickbooks.error'))
            ->with('error', $userFriendlyMessage)
            ->with('quickbooks_error_reason', 'callback_error');
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

