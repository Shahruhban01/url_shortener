<?php

namespace App\Http\Controllers;

use App\Models\ShortUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShortUrlController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'original_url' => 'required|url|max:2048',
        ]);

        // Users must be associated with a company to create short URLs.
        if (!$user->company_id) {
            abort(403, 'Your account is not associated with a company.');
        }

        // Generate a unique code for the short URL.
        do {
            $code = Str::random(8);
        } while (ShortUrl::where('code', $code)->exists());

        $shortUrl = new ShortUrl([
            'original_url' => $validated['original_url'],
            'code' => $code,
        ]);

        $shortUrl->user()->associate($user);
        $shortUrl->company()->associate($user->company);
        $shortUrl->save();

        $url = route('short-urls.redirect', [
            'code' => $shortUrl->code,
        ]);

        return back()->with('success', 'Short URL created: ' . $url);
    }

    public function redirect(string $code)
    {
        $shortUrl = ShortUrl::where('code', $code)->firstOrFail();

        $shortUrl->increment('clicks');

        return redirect()->away($shortUrl->original_url);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $shortUrls = ShortUrl::with(['user', 'company']);

        if ($user->role === 'super_admin') {
            // Super admins can view all short URLs.
        } elseif ($user->role === 'admin') {
            $shortUrls->where('company_id', $user->company_id);
        } else {
            $shortUrls->where('user_id', $user->id);
        }

        $shortUrls = $shortUrls->latest()->paginate(10);

        return view('short-urls.index', compact('shortUrls'));
    }
}
