<?php

namespace App\Http\Controllers;

use App\Models\ShortUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShortUrlController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'original_url' => [
                'required',
                'url',
                'max:2048',
            ],
        ]);

        // Admin and Member accounts must belong to a company.
        if (!$user->company_id) {
            abort(403, 'Your account is not associated with a company.');
        }

        do {
            $code = Str::random(8); // will generate a random 8-character string
        } while (ShortUrl::where('code', $code)->exists());

        $shortUrl = $user->shortUrls()->create([
            'company_id' => $user->company_id,
            'original_url' => $validated['original_url'],
            'code' => $code,
        ]);

        return back()->with(
            'success',
            'Short URL created: ' . route('short-urls.redirect', [
                'code' => $shortUrl->code,
            ])
        );
    }

    public function redirect(string $code)
    {
        $shortUrl = ShortUrl::where('code', $code)->firstOrFail();

        $shortUrl->increment('clicks');

        return redirect()->away($shortUrl->original_url);
    }
}