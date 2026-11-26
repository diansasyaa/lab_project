<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (! in_array($user->role, \App\Models\User::ROLES, true)) {
            abort(403, 'Akun ini belum memiliki role yang valid.');
        }

        return view('dashboards.'.$user->role, ['user' => $user]);
    }
}
