<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function storeCompanyInvitation(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
        ]);

        $plainToken = Str::random(64);

        [$company, $invitation] = DB::transaction(function () use (
            $validated,
            $request,
            $plainToken
        ) {
            $company = Company::create([
                'name' => $validated['company_name'],
            ]);

            $invitation = Invitation::create([
                'company_id' => $company->id,
                'invited_by' => $request->user()->id,
                'email' => $validated['email'],
                'role' => 'admin',
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours(48),
            ]);

            return [$company, $invitation];
        });

        $invitationUrl = route('invitations.accept', [
            'token' => $plainToken,
        ]);

        // Local development: write the email to Laravel's configured mailer.
        Mail::raw(
            "You have been invited to administer {$company->name}.\n\n"
                . "Accept your invitation using this link:\n"
                . $invitationUrl
                . "\n\nThis link expires in 48 hours.",
            function ($message) use ($invitation) {
                $message->to($invitation->email)
                    ->subject('Your company administrator invitation');
            }
        );

        return redirect()
            ->route('invitations.company.create')
            ->with('success', 'Company created and invitation generated. Check the configured mail output.');
    }

    
    public function storeCompanyMemberInvitation(Request $request)
    {
        $user = $request->user();

        if (!$user->company_id) {
            abort(403, 'Your account is not associated with a company.');
        }

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('invitations', 'email')
                    ->where('company_id', $user->company_id)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now()),
            ],
            'role' => ['required', Rule::in(['admin', 'member'])],
        ]);

        $plainToken = Str::random(64);

        $invitation = Invitation::create([
            'company_id' => $user->company_id,
            'invited_by' => $user->id,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours(48),
        ]);

        $invitationUrl = route('invitations.accept', [
            'token' => $plainToken,
        ]);

        Mail::raw(
            "You have been invited to join {$user->company->name} "
                . "as {$invitation->role}.\n\n"
                . "Accept your invitation using this link:\n"
                . $invitationUrl
                . "\n\nThis link expires in 48 hours.",
            function ($message) use ($invitation) {
                $message->to($invitation->email)
                    ->subject('Your company invitation');
            }
        );

        return back()->with(
            'success',
            'Invitation created. Check the configured mail output.'
        );
    }


    public function showAcceptanceForm(string $token)
    {
        $invitation = Invitation::with('company')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (
            !$invitation ||
            $invitation->accepted_at ||
            $invitation->expires_at->isPast()
        ) {
            abort(404, 'This invitation is invalid, expired, or already used.');
        }

        return view('invitations.accept', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        DB::transaction(function () use ($token, $validated) {
            $invitation = Invitation::where(
                'token_hash',
                hash('sha256', $token)
            )->lockForUpdate()->first();

            if (
                !$invitation ||
                $invitation->accepted_at ||
                $invitation->expires_at->isPast()
            ) {
                abort(404, 'This invitation is invalid, expired, or already used.');
            }

            if (User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'An account already exists for this invitation.',
                ]);
            }

            User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => Hash::make($validated['password']),
                'company_id' => $invitation->company_id,
                'role' => $invitation->role,
            ]);

            $invitation->update([
                'accepted_at' => now(),
            ]);
        });

        return redirect()->route('login')
            ->with('status', 'Invitation accepted. You can now log in.');
    }

    
    public function cancel(Request $request, int $id)
    {
        $user = $request->user();

        abort_unless(
            in_array($user->role, ['super_admin', 'admin'], true),
            403,
            'You are not allowed to cancel invitations.'
        );

        $invitation = Invitation::findOrFail($id);

        if ($user->role === 'admin') {
            abort_unless(
                $user->company_id &&
                $invitation->company_id === $user->company_id,
                403
            );
        }

        if ($invitation->accepted_at || $invitation->expires_at->isPast()) {
            return back()->with(
                'error',
                'Only pending invitations can be cancelled.'
            );
        }

        $invitation->delete();

        return back()->with('success', 'Invitation cancelled successfully.');
    }


    public function index(Request $request)
    {
        $user = $request->user();

        $query = Invitation::with(['company', 'inviter'])
            ->latest();

        if ($user->role === 'admin') {
            abort_unless($user->company_id, 403);

            $query->where('company_id', $user->company_id);
        } elseif ($user->role !== 'super_admin') {
            abort(403, 'You are not allowed to manage invitations.');
        }

        $invitations = $query->paginate(10);

        return view('invitations.index', compact('invitations'));
    }
}
