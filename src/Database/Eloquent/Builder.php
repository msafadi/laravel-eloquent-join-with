<?php

namespace Safadi\EloquentJoinWith\Database\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
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
    protected static $cachedColumns;

    /**
     * @var array
     */
    protected $appliedJoins = [];

    /**
     * @var array
     */
    protected $populatedJoins = [];

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

            $models = $builder->populateJoins($models);
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
    protected function applyJoinsWith(&$columns = ['*'])
    {
        if (empty($this->joinWith)) {
            return;
        }

        $columns = $this->applyQualifiedColumnName($columns, $this->getModel()->getTable());
        
        foreach ($this->joinWith as $name => $constraints) {
            // Skip nested relations
            if (Str::contains($name, '.')) {
                $parts = explode('.', $name);
                $parent = $this->getModel();
                foreach ($parts as $i => $n) {
                    $relation = $parent::query()->getRelation($n);
                    $this->applyRelationJoin($relation, $columns, static function() {});
                    $parent = $relation->getRelated();
                }
                continue;
            } else {
                $relation = $this->getRelation($name);

                $this->applyRelationJoin($relation, $columns, $constraints);
            }
        }
        return $this;
    }

    /**
     * @param Relation  $relation
     * @param array   $columns
     * @param Closure  $constraints 
     */
    protected function applyRelationJoin(Relation $relation, array &$columns, Closure $constraints)
    {
        if (!$relation instanceof HasOne && !$relation instanceof BelongsTo) {
            throw new RuntimeException(sprintf('joinWith: Only HasOne and BelgonsTo relations are supported, (%s) given.', get_class($relation)));
        }
        $key = get_class($relation->getParent()).'.'.get_class($relation->getRelated());
        if (in_array($key, $this->appliedJoins)) {
            return;
        }
        $this->appliedJoins[] = $key;

        $related = $relation->getRelated();
        $table = $related->getTable();
        $related_columns = $this->applyQualifiedColumnName($this->getColumnListing($table), $table, "{$table}_");
        $compareKey = method_exists($relation, 'getQualifiedOwnerKeyName')
            ? $relation->getQualifiedOwnerKeyName()
            : $relation->getQualifiedParentKeyName();

        $columns = array_merge($columns, $related_columns);
        $this
            ->select($columns)
            ->leftJoin($table, function($join) use($relation, $compareKey, $constraints) {
                $join->on(
                    $relation->getQualifiedForeignKeyName(), 
                    '=', 
                    $compareKey
                );
                $constraints($join);
            });
        
    }

    protected function populateJoins(array $models)
    {
        foreach ($models as $model) {
            $this->populatedJoins = [];
            $this->populateModel($model);
        }
        return $models;
    }

    protected function populateModel($model)
    {
        $attributes = $model->getAttributes();
        
        foreach ($this->joinWith as $name => $constraints) {
            // Nested relation
            if (Str::contains($name, '.')) {
                $parts = explode('.', $name);
                $parent = $this->getModel();
                foreach ($parts as $n) {
                    $parent = $this->populateRelated($parent, $n, $attributes);
                }
            } else {
                $this->populateRelated($model, $name, $attributes);
            }
        }
        $model->setRawAttributes($attributes);
    }

    protected function populateRelated($model, $name, &$attributes)
    {
        $relation = $model::query()->getRelation($name);
        $related = $relation->getRelated();

        $populatedKey = get_class($model).'.'.get_class($related);
        if (Arr::has($this->populatedJoins, $populatedKey)) {
            return $this->populatedJoins[$populatedKey];
        }
        
        $related_attributes = [];
        foreach ($attributes as $key => $value) {
            if (Str::startsWith($key, $related->getTable() . '_')) {
                $related_attributes[Str::remove($related->getTable() . '_', $key)] = $value;
                unset($attributes[$key]); // Remove relation attributes from the query results
            }
        }
        
        // No relation results, init the relation with default attributes if applicable
        if ($related_attributes[$related->getKeyName()] === null) {
            $relation->initRelation([$model], $name);
        } else {
            $model->setRelation($name, $related->setRawAttributes($related_attributes));
        }

        $this->populatedJoins[$populatedKey] = $related;
        return $related;
    }

    protected function applyQualifiedColumnName(array $columns, string $table, $as = '')
    {
        return array_map(function($column) use ($table, $as) {
            if (!Str::contains($column, '.')) {
                $column = "{$table}.{$column}" . ($as? " AS {$as}{$column}" : '');
            }
            return $column;
        }, $columns);
    }

    /**
     * @param string  $table
     * @return array
     */
    protected function getColumnListing($table)
    {
        if (!Arr::has(static::$cachedColumns, $table)) {
            static::$cachedColumns[$table] = $this->getConnection()->getSchemaBuilder()->getColumnListing($table);
        }
        return static::$cachedColumns[$table];
    }
}
