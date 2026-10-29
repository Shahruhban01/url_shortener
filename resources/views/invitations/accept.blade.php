<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation</title>
</head>
<body>
    <h1>Accept Company Invitation</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('invitations.accept.store', ['token' => $token]) }}">
        @csrf

        <p>Email: <strong>{{ $invitation->email }}</strong></p>
        <p>Company: <strong>{{ $invitation->company->name }}</strong></p>
        <p>Role: <strong>{{ $invitation->role }}</strong></p>

        <label for="name">Full name</label>
        <input id="name" name="name" value="{{ old('name') }}" required maxlength="255">

        <br><br>

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required minlength="12" autocomplete="new-password">

        <br><br>

        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">

        <br><br>

        <button type="submit">Accept Invitation</button>
    </form>
</body>
</html>