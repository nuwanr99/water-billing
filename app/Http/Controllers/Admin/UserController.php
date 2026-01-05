<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserPasswordRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Show the users list.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'created_at' => $user->created_at?->toFormattedDateString(),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the create user page.
     */
    public function create(): Response
    {
        return Inertia::render('admin/users/Create', [
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Store a new user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->except(['roles']));

        $user->syncRoles($request->validated('roles', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    /**
     * Show the edit user page.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/Edit', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'address' => $user->address,
                'wa_number' => $user->wa_number,
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
            ],
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Update the given user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->safe()->except(['roles']));

        $user->syncRoles($request->validated('roles', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    /**
     * Update the given user's password.
     */
    public function updatePassword(UpdateUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->validated('password')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('admin.users.edit', $user);
    }

    /**
     * Delete the given user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, __('You cannot delete your own account.'));

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }
}
