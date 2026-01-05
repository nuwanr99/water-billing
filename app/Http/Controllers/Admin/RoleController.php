<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteRoleRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Libraries\Datatable;
use App\Models\PermissionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Show the roles list.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['name'],
            orderColumns: ['name', 'permissions_count', 'created_at'],
            defaultSort: 'name',
        );

        $roles = $datatable
            ->paginate(Role::query()->withCount('permissions'))
            ->through(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'created_at' => $role->created_at?->toFormattedDateString(),
            ]);

        return Inertia::render('admin/roles/Index', [
            'roles' => $roles,
            'filters' => $datatable->filters(),
        ]);
    }

    /**
     * Show the create role page.
     */
    public function create(): Response
    {
        return Inertia::render('admin/roles/Create');
    }

    /**
     * Store a new role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created. You can now assign its permissions.')]);

        return to_route('admin.roles.edit', $role);
    }

    /**
     * Show the edit role page.
     */
    public function edit(Role $role): Response
    {
        $categories = PermissionCategory::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (PermissionCategory $category) => [
                $category->name => $category->permissions->pluck('name'),
            ]);

        return Inertia::render('admin/roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * Update the given role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('admin.roles.index');
    }

    /**
     * Sync the given role's permissions.
     */
    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        $role->syncPermissions($request->validated('permissions'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role permissions updated.')]);

        return to_route('admin.roles.edit', $role);
    }

    /**
     * Delete the given role.
     */
    public function destroy(DeleteRoleRequest $request, Role $role): RedirectResponse
    {
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }
}
