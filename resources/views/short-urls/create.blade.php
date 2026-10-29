
@extends('layouts.app')

@section('title', 'Create Short URL')
@section('page-heading', 'Create Short URL')

@section('content')

    <h3>Generate a New Short URL</h3>
    <p>Enter the original URL below to create a shortened link.</p>

    <form method="POST" action="{{ route('short-urls.store') }}">
        @csrf

        <table cellpadding="8">
            <tr>
                <td>
                    <label for="original_url">Original URL:</label>
                </td>
                <td>
                    <input
                        type="url"
                        name="original_url"
                        id="original_url"
                        value="{{ old('original_url') }}"
                        size="50"
                        required
                    >
                </td>
            </tr>

            <tr>
                <td></td>
                <td>
                    <button type="submit">Generate Short URL</button>
                </td>
            </tr>
        </table>
    </form>

    <p>
        <a href="{{ route('short-urls.index') }}">Back to Dashboard</a>
    </p>

@endsection
