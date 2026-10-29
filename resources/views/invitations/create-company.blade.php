
@extends('layouts.app')

@section('title', 'Create Company')
@section('page-heading', 'Create Company and Invite Admin')

@section('content')

    <h3>New Company Details</h3>
    <p>Create a company and invite its first administrator.</p>

    <form method="POST" action="{{ route('invitations.company.store') }}">
        @csrf

        <table cellpadding="8">
            <tr>
                <td>
                    <label for="company_name">Company Name:</label>
                </td>
                <td>
                    <input
                        type="text"
                        id="company_name"
                        name="company_name"
                        value="{{ old('company_name') }}"
                        size="40"
                        maxlength="255"
                        required
                    >
                </td>
            </tr>

            <tr>
                <td>
                    <label for="email">First Admin's Email:</label>
                </td>
                <td>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        size="40"
                        required
                    >
                </td>
            </tr>

            <tr>
                <td></td>
                <td>
                    <button type="submit">
                        Create Company and Invite Admin
                    </button>
                </td>
            </tr>
        </table>
    </form>

    <p>
        <a href="{{ route('short-urls.index') }}">Back to Dashboard</a>
    </p>

@endsection
