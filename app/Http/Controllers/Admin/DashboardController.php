<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'roles' => Role::count(),
                'permissions' => Permission::count(),
            ],
            'recentUsers' => User::query()
                ->with('roles:id,name')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'created_at' => $user->created_at?->toFormattedDateString(),
                ]),
        ]);
    }
}
