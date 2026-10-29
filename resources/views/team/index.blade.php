
@extends('layouts.app')

@section('title', 'Team Management')
@section('page-heading', 'Team Management')

@section('content')

    @if (auth()->user()->role === 'super_admin')

        <h3>All Companies</h3>

        @forelse ($companies as $company)
            <h4>{{ $company->name }}</h4>

            @if ($company->users->isNotEmpty())
                <table border="1" cellpadding="8" width="100%">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>

                    @foreach ($company->users as $member)
                        <tr>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>{{ $member->role }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <p>No users in this company.</p>
            @endif

            <hr>
        @empty
            <p>No companies found.</p>
        @endforelse

    @elseif (auth()->user()->role === 'admin')

        <h3>{{ $company->name }} Team</h3>

        <table border="1" cellpadding="8" width="100%">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
            </tr>

            @forelse ($company->users as $member)
                <tr>
                    <td>{{ $member->name }}</td>
                    <td>{{ $member->email }}</td>
                    <td>{{ $member->role }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No team members found.</td>
                </tr>
            @endforelse
        </table>

    @endif

@endsection
