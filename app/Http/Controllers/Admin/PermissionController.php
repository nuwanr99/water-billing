<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Libraries\Datatable;
use App\Models\Permission;
use App\Models\PermissionCategory;
use Illuminate\Database\Eloquent\Builder;
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
        $datatable = new Datatable(
            $request,
            searchColumns: ['name', 'category.name'],
            orderColumns: [
                'name',
                'created_at',
                'category' => fn (Builder $query, string $direction) => $query->orderBy(
                    PermissionCategory::select('name')->whereColumn('permission_categories.id', 'permissions.category_id'),
                    $direction === 'desc' ? 'desc' : 'asc',
                ),
            ],
            defaultSort: 'name',
        );

        $permissions = $datatable
            ->paginate(Permission::query()->with('category:id,name'))
            ->through(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'category' => $permission->category->name,
                'created_at' => $permission->created_at?->toFormattedDateString(),
            ]);

        return Inertia::render('admin/permissions/Index', [
            'permissions' => $permissions,
            'filters' => $datatable->filters(),
        ]);
    }
}
