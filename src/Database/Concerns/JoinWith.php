<?php

namespace Msafadi\LaravelJoinWith\Database\Concerns;

use Msafadi\LaravelJoinWith\Database\Eloquent\Builder;

trait JoinWith
{
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Msafadi\LaravelJoinWith\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
}
