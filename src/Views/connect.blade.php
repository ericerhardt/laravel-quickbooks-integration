@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Connect to QuickBooks Online</h4>
                </div>

                <div class="card-body text-center">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="mb-4">
                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiByeD0iMTAiIGZpbGw9IiMwMDc3QzUiLz4KPHN2ZyB4PSIyMCIgeT0iMjAiIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJ3aGl0ZSI+CjxwYXRoIGQ9Ik0xMiAyQzYuNDggMiAyIDYuNDggMiAxMnM0LjQ4IDEwIDEwIDEwIDEwLTQuNDggMTAtMTBTMTcuNTIgMiAxMiAyem0tMiAxNWwtNS01IDEuNDEtMS40MUwxMCAxNC4xN2w3LjU5LTcuNTlMMTkgOGwtOSA5eiIvPgo8L3N2Zz4KPC9zdmc+" 
                             alt="QuickBooks" class="mb-3" style="width: 80px; height: 80px;">
                    </div>

                    <h5 class="mb-3">Secure Connection to QuickBooks Online</h5>
                    
                    <p class="text-muted mb-4">
                        Connect your QuickBooks Online account to sync your financial data securely. 
                        This connection uses OAuth 2.0 for maximum security and will allow you to:
                    </p>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="feature-item mb-3">
                                <i class="fas fa-sync text-primary mb-2" style="font-size: 1.5rem;"></i>
                                <h6>Automatic Sync</h6>
                                <small class="text-muted">Keep your data synchronized in real-time</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-item mb-3">
                                <i class="fas fa-shield-alt text-success mb-2" style="font-size: 1.5rem;"></i>
                                <h6>Secure Access</h6>
                                <small class="text-muted">Bank-level security with OAuth 2.0</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-item mb-3">
                                <i class="fas fa-users text-info mb-2" style="font-size: 1.5rem;"></i>
                                <h6>Customer Management</h6>
                                <small class="text-muted">Sync customers and contacts</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-item mb-3">
                                <i class="fas fa-file-invoice text-warning mb-2" style="font-size: 1.5rem;"></i>
                                <h6>Invoice Integration</h6>
                                <small class="text-muted">Create and manage invoices</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <a href="{{ route('quickbooks.connect') }}" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-link me-2"></i>Connect to QuickBooks
                        </a>
                    </div>

                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            You will be redirected to QuickBooks to authorize this connection. 
                            No sensitive information is stored on our servers.
                        </small>
                    </div>

                    <hr>

                    <div class="row text-start">
                        <div class="col-md-6">
                            <h6>What We Access</h6>
                            <ul class="list-unstyled small text-muted">
                                <li><i class="fas fa-check text-success me-1"></i> Company information</li>
                                <li><i class="fas fa-check text-success me-1"></i> Customer data</li>
                                <li><i class="fas fa-check text-success me-1"></i> Invoice information</li>
                                <li><i class="fas fa-check text-success me-1"></i> Item catalog</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>What We Don't Access</h6>
                            <ul class="list-unstyled small text-muted">
                                <li><i class="fas fa-times text-danger me-1"></i> Bank account details</li>
                                <li><i class="fas fa-times text-danger me-1"></i> Credit card information</li>
                                <li><i class="fas fa-times text-danger me-1"></i> Tax returns</li>
                                <li><i class="fas fa-times text-danger me-1"></i> Personal financial data</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-4">
                        <small class="text-muted">
                            By connecting, you agree to our 
                            <a href="#" target="_blank">Terms of Service</a> and 
                            <a href="#" target="_blank">Privacy Policy</a>.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

