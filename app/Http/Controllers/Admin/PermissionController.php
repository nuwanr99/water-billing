<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    /**
     * Show the permissions list.
     *
     * Permissions and their categories are defined in code (see RolePermissionSeeder),
     * so the admin interface only lists them.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $permissions = Permission::query()
            ->with('category:id,name')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'category' => $permission->category->name,
                'created_at' => $permission->created_at?->toFormattedDateString(),
            ]);

        return Inertia::render('admin/permissions/Index', [
            'permissions' => $permissions,
            'filters' => ['search' => $search],
        ]);
    }
}
