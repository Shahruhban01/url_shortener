<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Short URL</title>
</head>
<body>
    <h1>Create Short URL</h1>

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

    <form method="POST" action="{{ route('short-urls.store') }}">
        @csrf

        <label for="original_url">Original URL</label>
        <input
            type="url"
            name="original_url"
            id="original_url"
            value="{{ old('original_url') }}"
            required
        >

        <button type="submit">Generate Short URL</button>
    </form>
</body>
</html>