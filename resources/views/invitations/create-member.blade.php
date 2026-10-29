
@extends('layouts.app')

@section('title', 'Invite Company User')
@section('page-heading', 'Invite a User to Your Company')

@section('content')

    <h3>Invite a Team Member</h3>
    <p>Enter the email address and select the role for the new user.</p>

    <form method="POST" action="{{ route('invitations.member.store') }}">
        @csrf

        <table cellpadding="8">
            <tr>
                <td>
                    <label for="email">Email Address:</label>
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
                <td>
                    <label for="role">Role:</label>
                </td>
                <td>
                    <select id="role" name="role" required>
                        <option value="member" {{ old('role', 'member') === 'member' ? 'selected' : '' }}>
                            Member
                        </option>

                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>
                            Admin
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <td></td>
                <td>
                    <button type="submit">Send Invitation</button>
                </td>
            </tr>
        </table>
    </form>

    <p>
        <a href="{{ route('short-urls.index') }}">Back to Dashboard</a>
    </p>

@endsection
