
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'URL Shortener')</title>
</head>
<body>

    <table width="100%" cellpadding="10">
        <tr>
            <td colspan="2">
                <h2>URL Shortener</h2>
                <hr>
            </td>
        </tr>

        <tr>
            <td width="220" valign="top">
                <h3>Navigation</h3>

                <p><a href="{{ route('short-urls.index') }}">Dashboard / Short URLs</a></p>

                @if (in_array(auth()->user()->role, ['admin', 'member'], true))
                    <p><a href="{{ route('short-urls.create') }}">Create Short URL</a></p>
                @endif

                @if (auth()->user()->role === 'super_admin')
                    <p>
                        <a href="{{ route('invitations.company.create') }}">
                            Create Company
                        </a>
                    </p>
                @endif

                @if (in_array(auth()->user()->role, ['super_admin', 'admin'], true))
                    <p>
                        <a href="{{ route('invitations.index') }}">Manage Invitations</a>
                    </p>
                @endif

                @if (auth()->user()->role === 'admin')
                    <p>
                        <a href="{{ route('team.index') }}">Team Management</a>
                    </p>

                    <p>
                        <a href="{{ route('invitations.member.create') }}">Invite Team Member</a>
                    </p>
                @endif


                <p><a href="{{ route('profile.edit') }}">Profile</a></p>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Logout</button>
                </form>
            </td>

            <td valign="top">
                <h2>@yield('page-heading', 'Dashboard')</h2>

                <p>
                    Logged in as: {{ auth()->user()->name }}
                    ({{ auth()->user()->role }})
                </p>

                <hr>

                @if (session('success'))
                    <p>{{ session('success') }}</p>
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
