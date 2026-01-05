<?php

namespace App\Libraries;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Server-side search, sorting, and pagination for the admin datatables.
 *
 * Ported from the reference application's Datatable library, adapted to return
 * Inertia-friendly paginators instead of AJAX datatable responses.
 */
class Datatable
{
    protected string $search;

    protected ?string $sort;

    /** @var 'asc'|'desc'|null */
    protected ?string $direction;

    /** @var array<string, list<literal-string>|Closure> */
    protected array $sortable;

    /** @var list<literal-string|list<literal-string>> */
    protected array $searchColumns;

    /**
     * @param  list<literal-string|list<literal-string>>  $searchColumns  Columns to search: plain columns, composite
     *                                                                    column groups (matched as one concatenated
     *                                                                    string), or "relation.column" paths.
     * @param  array<int|string, literal-string|list<literal-string>|Closure>  $orderColumns  Whitelist of sortable keys. String entries
     *                                                                                        sort by the column itself; keyed entries map
     *                                                                                        to the columns (or closure) to order by.
     * @param  'asc'|'desc'  $defaultDirection
     */
    public function __construct(
        Request $request,
        array $searchColumns = [],
        array $orderColumns = [],
        protected ?string $defaultSort = null,
        protected string $defaultDirection = 'asc',
    ) {
        $this->searchColumns = $searchColumns;
        $this->sortable = $this->normalizeOrderColumns($orderColumns);

        $this->search = $request->string('search')->trim()->value();

        $sort = $request->string('sort')->value();
        $this->sort = array_key_exists($sort, $this->sortable) ? $sort : null;

        $direction = $request->string('direction')->value();
        $this->direction = match (true) {
            $this->sort === null => null,
            in_array($direction, ['asc', 'desc'], true) => $direction,
            default => 'asc',
        };
    }

    /**
     * Apply the search and sort to the query and paginate it.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query, int $perPage = 10): LengthAwarePaginator
    {
        $this->applySearch($query);
        $this->applyOrder($query);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get the active filters to share with the page.
     *
     * @return array{search: string, sort: string|null, direction: 'asc'|'desc'|null}
     */
    public function filters(): array
    {
        return [
            'search' => $this->search,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
    }

    /**
     * Search every configured column for the requested term.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function applySearch(Builder $query): void
    {
        if ($this->search === '') {
            return;
        }

        $term = "%{$this->search}%";

        $query->where(function (Builder $query) use ($term) {
            foreach ($this->searchColumns as $column) {
                if (is_array($column)) {
                    $query->orWhereRaw($this->concatenate($query, $column).' like ?', [$term]);
                } elseif (str_contains($column, '.')) {
                    [$relation, $columnName] = explode('.', $column, 2);

                    $query->orWhereHas($relation, fn (Builder $subQuery) => $subQuery->where($columnName, 'like', $term));
                } else {
                    $query->orWhere($column, 'like', $term);
                }
            }
        });
    }

    /**
     * Order by the requested column when it is sortable, or the default.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyOrder(Builder $query): void
    {
        $sort = $this->sort ?? $this->defaultSort;

        if ($sort === null || ! array_key_exists($sort, $this->sortable)) {
            return;
        }

        $direction = $this->direction ?? $this->defaultDirection;
        $columns = $this->sortable[$sort];

        if ($columns instanceof Closure) {
            $columns($query, $direction);

            return;
        }

        foreach ($columns as $column) {
            $query->orderBy($column, $direction);
        }
    }

    /**
     * Expand shorthand entries so every sortable key maps to its columns.
     *
     * @param  array<int|string, literal-string|list<literal-string>|Closure>  $orderColumns
     * @return array<string, list<literal-string>|Closure>
     */
    protected function normalizeOrderColumns(array $orderColumns): array
    {
        $sortable = [];

        foreach ($orderColumns as $key => $value) {
            if (is_string($key)) {
                $sortable[$key] = $value instanceof Closure || is_array($value) ? $value : [$value];
            } elseif (is_string($value)) {
                $sortable[$value] = [$value];
            }
        }

        return $sortable;
    }

    /**
     * Build a driver-appropriate SQL expression joining columns with spaces.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<literal-string>  $columns
     * @return literal-string
     */
    protected function concatenate(Builder $query, array $columns): string
    {
        return match ($query->getModel()->getConnection()->getDriverName()) {
            'sqlite' => implode(" || ' ' || ", $columns),
            default => "CONCAT_WS(' ', ".implode(', ', $columns).')',
        };
    }
}
