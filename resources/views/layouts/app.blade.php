<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'URL Shortener')</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #263238;
        }

        .header {
            background: #1e293b;
            color: white;
            padding: 15px 24px;
        }

        .navigation {
            background: white;
            border-right: 1px solid #ddd;
            padding: 15px;
        }

        .navigation a {
            color: #2563eb;
            text-decoration: none;
        }

        .navigation a:hover {
            text-decoration: underline;
        }

        .content {
            padding: 20px;
        }

        .data-table {
            border-collapse: collapse;
            background: white;
            border: 1px solid #d5dbe5;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #d5dbe5;
            padding: 9px;
        }

        .data-table th {
            background: #e8edf5;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 7px 12px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        input,
        select {
            padding: 7px;
            border: 1px solid #cbd5e1;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 10px;
        }

        hr {
            border: none;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    @php
        $role = auth()->user()->role;
        $isSuperAdmin = $role === 'super_admin';
        $isAdmin = $role === 'admin';
        $canCreateUrls = in_array($role, ['admin', 'member'], true);
        $canManageTeam = in_array($role, ['super_admin', 'admin'], true);
    @endphp

    <table width="100%" cellpadding="10">
        <tr>
            <td colspan="2" class="header">
                <h2>URL Shortener</h2>
                <hr>
            </td>
        </tr>

        <tr>
            <td width="220" valign="top" class="navigation">
                <h3>Navigation</h3>

                <p><a href="{{ route('short-urls.index') }}">Dashboard / Short URLs</a></p>

                @if ($canCreateUrls)
                    <p><a href="{{ route('short-urls.create') }}">Create Short URL</a></p>
                @endif

                @if ($isSuperAdmin)
                    <p><a href="{{ route('invitations.company.create') }}">Create Company</a></p>
                    <p><a href="{{ route('team.index') }}">Companies</a></p>
                @elseif ($isAdmin)
                    <p><a href="{{ route('team.index') }}">Team Management</a></p>
                    <p><a href="{{ route('invitations.member.create') }}">Invite Team Member</a></p>
                @endif

                @if ($canManageTeam)
                    <p><a href="{{ route('invitations.index') }}">Manage Invitations</a></p>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Logout</button>
                </form>
            </td>

            <td valign="top" class="content">
                <h2>@yield('page-heading', 'Dashboard')</h2>

                <p>
                    Logged in as: {{ auth()->user()->name }}
                    ({{ $role }})
                </p>

                <hr>

                @if (session('success'))
                    <p class="message">{{ session('success') }}</p>
                @endif

                @if (session('error'))
                    <p>{{ session('error') }}</p>
                @endif

                @if ($errors->any())
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                @yield('content')
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <hr>
                <p align="center">URL Shortener Application</p>
            </td>
        </tr>
    </table>
</body>
</html>