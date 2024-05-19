<?php

namespace Safadi\EloquentJoinWith\Database\Concerns;

use Safadi\EloquentJoinWith\Database\Eloquent\Builder;

trait JoinWith
{

    /**
     * Begin querying a model with join relations.
     *
     * @param  array|string  $relations
     * @return \Safadi\EloquentJoinWith\Database\Eloquent\Builder
     */
    public static function joinWith($relations)
    {
        return static::query()->joinWith(
            is_string($relations) ? func_get_args() : $relations
        );
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Safadi\EloquentJoinWith\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
}
