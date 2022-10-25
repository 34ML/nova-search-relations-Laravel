<?php

namespace _34ml\SearchRelations\Searches;

use Illuminate\Database\Eloquent\Builder;
use _34ml\SearchRelations\Contracts\Search;
use Illuminate\Support\Facades\DB;

class ColumnSearch implements Search
{
    /**
     * Searchable columns.
     *
     * @var array
     */
    protected $columns;

    /**
     * Instantiate a new search query.
     *
     * @param array $columns
     */
    public function __construct(array $columns)
    {
        $this->columns = $columns;
    }

    /**
     * Apply search for the given relation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $relation
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $query, string $relation, string $search): Builder
    {
        return $query->where(function ($query) use ($search) {
            return $this->applySearchQuery($query, $search);
        });
    }

    /**
     * Apply search query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applySearchQuery(Builder $query, string $search): Builder
    {
        $model = $query->getModel();
        $operator = $this->operator($query);

        foreach ($this->columns as $column) {
            $query->orWhere(
                \DB::raw('LOWER(' . $model->getConnection()->getQueryGrammar()->wrap($model->qualifyColumn($column)) . ')'),
                $operator,
                static::searchableKeyword($column, strtolower($search)));
        }

        return $query;
    }

    protected static function searchableKeyword($column, $search)
    {
        return '%'.$search.'%';
    }

    /**
     * Get the like operator for the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return string
     */
    protected function operator(Builder $query): string
    {
        if ($query->getModel()->getConnection()->getDriverName() === 'sqlite') {
            return 'ILIKE';
        }

        return 'LIKE';
    }
}
