<?php

namespace Msafadi\LaravelJoinWith\Database\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class Builder extends EloquentBuilder
{
    /**
     * @var array<string>
     */
    protected $joinWith = [];

    /**
     * @var array<string>
     */
    protected $cachedColumns;

    /**
     * Set the relationships that should joined, only HasOne and BelongsTo relations are supported.
     *
     * @param  string|array  $relations
     * @param  string|\Closure|null  $callback
     * @return $this
     */
    public function joinWith($relations, $callback = null)
    {
        if ($callback instanceof Closure) {
            $joinWith = $this->parseWithRelations([$relations => $callback]);
        } else {
            $joinWith = $this->parseWithRelations(is_string($relations) ? func_get_args() : $relations);
        }

        $this->joinWith = array_merge($this->joinWith, $joinWith);

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();

        $builder->applyJoinsWith($columns);

        if (count($models = $builder->getModels($columns)) > 0) {
            // Keep original eager load of relations
            $models = $builder->eagerLoadRelations($models);

            $models = $builder->hydrateJoins($models);
        }

        return $this->applyAfterQueryCallbacks(
            $builder->getModel()->newCollection($models)
        );
    }

    /**
     * @param array  $columns
     * @return Builder
     * 
     * @throws \RuntimeException
     */
    protected function applyJoinsWith($columns = ['*'])
    {
        if (empty($this->joinWith)) {
            return;
        }

        $columns = $this->applyQualifiedColumnName($columns, $this->getModel()->getTable());
        
        foreach ($this->joinWith as $name => $constraints) {
            // Skip nested relations
            if (Str::contains($name, '.')) {
                continue;
            }
            $relation = $this->getRelation($name);

            if (!$relation instanceof HasOne && !$relation instanceof BelongsTo) {
                throw new RuntimeException(sprintf('joinWith: Only HasOne and BelgonsTo relations are supported, (%s) given.', get_class($relation)));
            }

            $related =  $relation->getRelated();
            $table =  $related->getTable();
            $related_columns = $this->listColumns($table);
            $related_columns = $this->applyQualifiedColumnName($related_columns, $table, "{$table}_");
            $key = method_exists($relation, 'getQualifiedOwnerKeyName')
                ? $relation->getQualifiedOwnerKeyName()
                : $relation->getQualifiedParentKeyName();

            $this
                ->addSelect($columns)
                ->addSelect($related_columns)
                ->leftJoin(
                    $table, 
                    $relation->getQualifiedForeignKeyName(), 
                    '=', 
                    $key
                );
            $constraints($this);
        }
    }

    protected function hydrateJoins(array $models)
    {
        $result = [];
        foreach ($models as $model) {
            $attributes = $model->getAttributes();
            $model = $model->newInstance([]);
            foreach ($this->joinWith as $name => $constraints) {
                // Skip nested relations
                if (Str::contains($name, '.')) {
                    continue;
                }
                $relation = $this->getRelation($name);
                $related = clone $relation->getRelated();
                $related_attributes = [];
                foreach ($attributes as $key => $value) {
                    if (Str::startsWith($key, $related->getTable() . '_')) {
                        $related_attributes[Str::remove($related->getTable() . '_', $key)] = $value;
                        unset($attributes[$key]);
                    }
                }
                if ($related_attributes[$related->getKeyName()] == null) {
                    $relation->initRelation([$model], $name);
                } else {
                    $model->setRelation($name, $related->forceFill($related_attributes));
                }
            }
            $model->forceFill($attributes);
            $result[] = $model;
        }
        return $result;
    }

    protected function applyQualifiedColumnName(array $columns, string $table, $as = '')
    {
        return array_map(function($column) use ($table, $as) {
            if (!Str::contains($column, '.')) {
                $column = ($table . '.' . $column) . ($as? ' AS ' . $as . $column : '');
            }
            return $column;
        }, $columns);
    }

    /**
     * @param string  $table
     * @return array
     */
    protected function listColumns($table)
    {
        if (!Arr::has($this->cachedColumns, $table)) {
            $this->cachedColumns[$table] = $this->getConnection()->getSchemaBuilder()->getColumnListing($table);
        }
        return $this->cachedColumns[$table];
    }
}
