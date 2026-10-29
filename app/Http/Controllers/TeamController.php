<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // SuperAdmin can view all companies and their users
        if ($user->role === 'super_admin') {
            $companies = Company::with('users')
                ->orderBy('name')
                ->get();

            return view('team.index', compact('companies'));
        }

        // Company Admin can view only their own company's team
        if ($user->role === 'admin') {
            abort_unless($user->company_id, 403);

            $company = Company::with('users')
                ->findOrFail($user->company_id);

            return view('team.index', compact('company'));
        }

        // All other roles are denied access
        abort(403, 'You are not allowed to manage the team.');
    }
}
