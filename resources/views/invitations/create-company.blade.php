<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Company and Invite Admin</title>
</head>
<body>
    <h1>Create Company and Invite Admin</h1>

    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('invitations.company.store') }}">
        @csrf

        <div>
            <label for="company_name">Company name</label>
            <input
                type="text"
                id="company_name"
                name="company_name"
                value="{{ old('company_name') }}"
                required
                maxlength="255"
            >
        </div>

        <div>
            <label for="email">First Admin's email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                required
            >
        </div>

        <button type="submit">Create Company and Invite Admin</button>
    </form>
</body>
</html>