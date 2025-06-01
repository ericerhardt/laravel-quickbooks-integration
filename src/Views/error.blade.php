@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h4><i class="fas fa-exclamation-triangle"></i> QuickBooks Connection Error</h4>
                </div>

                <div class="card-body">
                    <div class="alert alert-danger">
                        <h5>Connection Failed</h5>
                        <p class="mb-0">{{ $error }}</p>
                    </div>

                    @if($reason === 'no_token')
                        <div class="mb-4">
                            <h6>What does this mean?</h6>
                            <p>Your application is not connected to QuickBooks Online. You need to establish a connection before you can access QuickBooks data.</p>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('quickbooks.connect') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-link"></i> Connect to QuickBooks
                            </a>
                        </div>

                    @elseif($reason === 'token_expired' || $reason === 'refresh_token_expired')
                        <div class="mb-4">
                            <h6>What does this mean?</h6>
                            <p>Your QuickBooks connection has expired. This happens automatically for security reasons. You need to reconnect to continue using QuickBooks features.</p>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('quickbooks.connect') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-sync"></i> Reconnect to QuickBooks
                            </a>
                        </div>

                    @elseif($reason === 'token_invalid')
                        <div class="mb-4">
                            <h6>What does this mean?</h6>
                            <p>Your QuickBooks connection token is invalid. This can happen if the connection was revoked from QuickBooks or if there was a technical issue.</p>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('quickbooks.connect') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-redo"></i> Reconnect to QuickBooks
                            </a>
                        </div>

                    @elseif($reason === 'refresh_failed')
                        <div class="mb-4">
                            <h6>What does this mean?</h6>
                            <p>We tried to refresh your QuickBooks connection automatically, but it failed. This usually means you need to reconnect manually.</p>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('quickbooks.connect') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-link"></i> Reconnect to QuickBooks
                            </a>
                        </div>

                    @elseif($reason === 'callback_error')
                        <div class="mb-4">
                            <h6>What does this mean?</h6>
                            <p>There was an error during the QuickBooks connection process. This could be due to a temporary issue or if you denied access to your QuickBooks account.</p>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('quickbooks.connect') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-retry"></i> Try Again
                            </a>
                        </div>

                    @else
                        <div class="mb-4">
                            <h6>What can you do?</h6>
                            <p>There was an unexpected error with your QuickBooks connection. Please try reconnecting or contact support if the problem persists.</p>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('quickbooks.connect') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-link"></i> Connect to QuickBooks
                            </a>
                        </div>
                    @endif

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <h6>Need Help?</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-book"></i> <a href="#" target="_blank">View Documentation</a></li>
                                <li><i class="fas fa-question-circle"></i> <a href="#" target="_blank">FAQ</a></li>
                                <li><i class="fas fa-envelope"></i> <a href="mailto:support@example.com">Contact Support</a></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Quick Actions</h6>
                            <div class="d-grid gap-2">
                                <a href="{{ route('quickbooks.status') }}" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-info-circle"></i> Check Connection Status
                                </a>
                                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> Go Back
                                </a>
                            </div>
                        </div>
                    </div>

                    @if(config('app.debug') && isset($reason))
                        <div class="mt-4">
                            <details>
                                <summary class="text-muted">Debug Information</summary>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <strong>Error Reason:</strong> {{ $reason }}<br>
                                        <strong>Timestamp:</strong> {{ now()->format('Y-m-d H:i:s') }}<br>
                                        <strong>User Agent:</strong> {{ request()->userAgent() }}
                                    </small>
                                </div>
                            </details>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

