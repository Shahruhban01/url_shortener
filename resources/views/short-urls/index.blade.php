
@extends('layouts.app')

@section('title', 'Short URLs')
@section('page-heading', 'Short URLs Dashboard')

@section('content')

    @if ($shortUrls->count())
        <table class="data-table" cellpadding="10" width="100%">
            <thead>
                <tr>
                    <th>Short Code</th>
                    <th>Original URL</th>
                    <th>Company</th>
                    <th>Created By</th>
                    <th>Clicks</th>
                    <th>Created At</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($shortUrls as $shortUrl)
                    <tr>
                        <td>
                            <a href="{{ route('short-urls.redirect', ['code' => $shortUrl->code]) }}"
                               target="_blank">
                                {{ $shortUrl->code }}
                            </a>
                        </td>
                        <td>{{ $shortUrl->original_url }}</td>
                        <td>{{ $shortUrl->company->name }}</td>
                        <td>{{ $shortUrl->user->name }}</td>
                        <td>{{ $shortUrl->clicks }}</td>
                        <td>{{ $shortUrl->created_at->format('d M Y, h:i A') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $shortUrls->links() }}
    @else
        <p>No short URLs found.</p>
    @endif

@endsection
