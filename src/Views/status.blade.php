@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>QuickBooks Connection Status</h4>
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    @if($status['connected'])
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle"></i> Connected to QuickBooks</h5>
                            <p class="mb-0">Your application is successfully connected to QuickBooks Online.</p>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h6>Connection Details</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th>Company:</th>
                                        <td>{{ $status['company_name'] ?? 'Unknown' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Company ID:</th>
                                        <td><code>{{ $status['realm_id'] }}</code></td>
                                    </tr>
                                    <tr>
                                        <th>Access Token Expires:</th>
                                        <td>
                                            {{ $status['access_token_expires_at']?->format('M j, Y g:i A') }}
                                            @if($status['needs_refresh'])
                                                <span class="badge bg-warning">Expired</span>
                                            @else
                                                <span class="badge bg-success">Valid</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Refresh Token Expires:</th>
                                        <td>{{ $status['refresh_token_expires_at']?->format('M j, Y g:i A') }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Actions</h6>
                                <div class="d-grid gap-2">
                                    @if($status['needs_refresh'])
                                        <button type="button" class="btn btn-warning" onclick="refreshToken()">
                                            <i class="fas fa-sync"></i> Refresh Token
                                        </button>
                                    @endif
                                    
                                    <form method="POST" action="{{ route('quickbooks.disconnect') }}" 
                                          onsubmit="return confirm('Are you sure you want to disconnect from QuickBooks?')">
                                        @csrf
                                        <button type="submit" class="btn btn-danger w-100">
                                            <i class="fas fa-unlink"></i> Disconnect
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-triangle"></i> Not Connected to QuickBooks</h5>
                            <p>Your application is not connected to QuickBooks Online. Connect now to start syncing your data.</p>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('quickbooks.connect') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-link"></i> Connect to QuickBooks
                            </a>
                        </div>

                        <div class="mt-4">
                            <h6>What happens when you connect?</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Secure OAuth 2.0 authentication</li>
                                <li><i class="fas fa-check text-success"></i> Access to your QuickBooks company data</li>
                                <li><i class="fas fa-check text-success"></i> Automatic data synchronization</li>
                                <li><i class="fas fa-check text-success"></i> Real-time updates between systems</li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($status['connected'])
<script>
function refreshToken() {
    fetch('{{ route("quickbooks.refresh-token") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to refresh token: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error refreshing token: ' + error.message);
    });
}
</script>
@endif
@endsection

