<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
}
