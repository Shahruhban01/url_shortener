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

        // $shortUrl = $user->shortUrls()->create([
        //     'company_id' => $user->company_id,
        //     'original_url' => $validated['original_url'],
        //     'code' => $code,
        // ]);
        $shortUrl = new ShortUrl([
            'original_url' => $validated['original_url'],
            'code' => $code,
        ]);

        $shortUrl->company()->associate($user->company);
        $shortUrl->user()->associate($user);
        $shortUrl->save();

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

    
public function index(Request $request)
{
    $user = $request->user();

    if ($user->role === 'super_admin') {
        $shortUrls = ShortUrl::with(['user', 'company'])
            ->latest()
            ->paginate(10);
    } elseif ($user->role === 'admin') {
        $shortUrls = ShortUrl::with(['user', 'company'])
            ->where('company_id', $user->company_id)
            ->latest()
            ->paginate(10);
    } else {
        $shortUrls = ShortUrl::with(['user', 'company'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);
    }

    return view('short-urls.index', compact('shortUrls'));
}

}