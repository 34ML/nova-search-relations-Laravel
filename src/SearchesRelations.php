<?php

namespace _34ml\SearchRelations;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Titasgailius\SearchRelations\Contracts\Search;
use Titasgailius\SearchRelations\Searches\RelationSearch;

trait SearchesRelations
{
    /**
     * Determine if this resource is searchable.
     *
     * @return bool
     */
    public static function searchable()
    {
        return parent::searchable()
            || ! empty(static::resolveSearchableRelations());
    }

    /**
     * Get the searchable columns for the resource.
     *
     * @return array
     */
    public static function searchableRelations(): array
    {
        return static::$searchRelations ?? [];
    }

    /**
     * Get the globally searchable relations for the resource.
     *
     * @return array
     */
    public static function globallySearchableRelations(): array
    {
        if (isset(static::$globalSearchRelations)) {
            return static::$globalSearchRelations;
        }

        if (static::$searchRelationsGlobally ?? true) {
            return static::searchableRelations();
        }

        return [];
    }

    /**
     * Resolve searchable relations for the current request.
     *
     * @return array
     */
    protected static function resolveSearchableRelations(): array
    {
        return static::isGlobalSearch()
            ? static::globallySearchableRelations()
            : static::searchableRelations();
    }

    /**
     * Determine whether current request is for global search.
     *
     * @return bool
     */
    protected static function isGlobalSearch()
    {
        return request()->route()->action['uses'] === 'Laravel\Nova\Http\Controllers\SearchController@index';
    }

    /**
     * Apply the search query to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function applySearch($query, $search)
    {
        return $query
            ->where(function ($query) use ($search) {
                $model = $query
                    ->getModel();

                $connectionType = $model
                    ->getConnection()
                    ->getDriverName();

                $canSearchPrimaryKey = ctype_digit($search) &&
                    in_array($model->getKeyType(), ['int', 'integer']) &&
                    ($connectionType != 'sqlite' || $search <= static::maxPrimaryKeySize()) &&
                    in_array($model->getKeyName(), static::$search);

                if ($canSearchPrimaryKey) {
                    $query->orWhere($model->getQualifiedKeyName(), $search);
                }

                $likeOperator = $connectionType == 'sqlite' ? 'ilike' : 'like';

                foreach (static::searchableColumns() as $column) {
                    $query->orWhere(
                        \DB::raw('LOWER(' . $model
                                ->getConnection()
                                ->getQueryGrammar()
                                ->wrap($model
                                    ->qualifyColumn($column)) . ')'),
                        $likeOperator,
                        static::searchableKeyword($column, strtolower($search))
                    );
                }
                static::applyRelationSearch($query, $search);
            });
    }

    /**
     * Apply the relationship search query to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function applyRelationSearch(Builder $query, string $search): Builder
    {
        foreach (static::resolveSearchableRelations() as $relation => $columns) {
            static::parseSearch($relation, $columns)->apply($query, $relation, $search);
        }

        return $query;
    }

    /**
     * Parse search.
     *
     * @param  string $relation
     * @param  mixed $columns
     * @return \Titasgailius\SearchRelations\Contracts\Search
     */
    protected static function parseSearch($relation, $columns): Search
    {
        if ($columns instanceof Search) {
            return $columns;
        }

        if (is_array($columns)) {
            return new RelationSearch($columns);
        }

        throw new InvalidArgumentException(sprintf(
            'Unsupported search configuration in [%s] resource for [%s] relationship.',
            static::class, $relation
        ));
    }
}
