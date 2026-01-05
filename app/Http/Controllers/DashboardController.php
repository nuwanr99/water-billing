<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard for normal users, sending admins to theirs.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()->can('admin')) {
            return to_route('admin.dashboard');
        }

        return Inertia::render('Dashboard');
    }
}
