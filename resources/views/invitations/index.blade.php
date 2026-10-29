
@extends('layouts.app')

@section('title', 'Manage Invitations')
@section('page-heading', 'Invitation Management')

@section('content')

    <h3>All Invitations</h3>

    <table border="1" cellpadding="8" width="100%">
        <thead>
            <tr>
                <th>Email</th>
                <th>Company</th>
                <th>Role</th>
                <th>Invited By</th>
                <th>Status</th>
                <th>Expires At</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($invitations as $invitation)
                <tr>
                    <td>{{ $invitation->email }}</td>
                    <td>{{ $invitation->company->name }}</td>
                    <td>{{ $invitation->role }}</td>
                    <td>{{ $invitation->inviter->name }}</td>
                    <td>
                        @if ($invitation->accepted_at)
                            Accepted
                        @elseif ($invitation->expires_at->isPast())
                            Expired
                        @else
                            Pending
                        @endif
                    </td>
                    <td>{{ $invitation->expires_at->format('d M Y, h:i A') }}</td>

                    <td>
                    @if (!$invitation->accepted_at && !$invitation->expires_at->isPast())
                        <form method="POST"
                            action="{{ route('invitations.cancel', $invitation->id) }}">
                            @csrf
                            @method('DELETE')

                            <button type="submit">Cancel Invitation</button>
                        </form>
                    @else
                        Accepted/Expired
                    @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No invitations found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $invitations->links() }}

@endsection
