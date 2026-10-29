<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'super_admin') {
            $companies = Company::with('users')
                ->orderBy('name')
                ->get();

            return view('team.index', compact('companies'));
        }

        if ($user->role === 'admin') {
            abort_unless($user->company_id, 403);

            $company = Company::with('users')
                ->findOrFail($user->company_id);

            return view('team.index', compact('company'));
        }

        abort(403, 'You are not allowed to manage the team.');
    }
}
