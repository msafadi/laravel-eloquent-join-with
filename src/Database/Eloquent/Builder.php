<?php

namespace Safadi\EloquentJoinWith\Database\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use RuntimeException;

class Builder extends EloquentBuilder
{
    /**
     * @var array
     */
    protected $joinWith = [];

    /**
     * @var array
     */
    protected static $cachedColumns = [];

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
        }
        
        return $this->applyAfterQueryCallbacks(
            $builder->getModel()->newCollection($models)
        );
    }

    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Database\Eloquent\Model[]|static[]
     */
    public function getModels($columns = ['*'])
    {
        return $this->hydrate(
            $this->query->get($columns)->all()
        )->all();
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param  array  $items
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function hydrate(array $items)
    {
        $instance = $this->newModelInstance();

        return $instance->newCollection(array_map(function ($item) use ($items, $instance) {
            
            $model = $instance->newFromBuilder($item);
            $this->populateModel($model, $item);

            if (count($items) > 1) {
                $model->preventsLazyLoading = Model::preventsLazyLoading();
            }

            return $model;
        }, $items));
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
            if (strpos($name, '.') !== false) {
                $parent = $this->getModel();
                foreach (explode('.', $name) as $name) {
                    $relation = $parent::query()->getRelation($name);
                    $this->applyRelationJoin($relation, $columns, static function() {});
                    $parent = $relation->getRelated();
                }
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
        $related_columns = $this->applyQualifiedColumnName($this->getColumnListing($table), $table, $table.'_');

        $columns = array_merge($columns, $related_columns);

        $this->leftJoin($table, function($join) use($relation, $constraints) {
            $compareKey = $relation instanceof BelongsTo
                ? $relation->getQualifiedOwnerKeyName()
                : $relation->getQualifiedParentKeyName();

            $join->on(
                $relation->getQualifiedForeignKeyName(), 
                '=', 
                $compareKey
            );
            // Apply realtion constraints on the join clause
            $constraints($join);
        });
        
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param object|array $item
     */
    protected function populateModel($model, $item)
    {
        $attributes = (array) $item;
        $this->populatedJoins = [];
        
        foreach ($this->joinWith as $name => $constraints) {   
            // Nested relation
            if (strpos($name, '.') !== false) {
                $parent = $this->getModel();
                foreach (explode('.', $name) as $name) {
                    $parent = $this->populateRelated($parent, $name, $attributes);
                }
            } else {
                $this->populateRelated($model, $name, $attributes);
            }
        }
        $model->setRawAttributes($attributes, true);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $name Relation name
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function populateRelated($model, $name, &$attributes)
    {
        $relation = $model::query()->getRelation($name);
        $related = $relation->getRelated();

        $populatedKey = get_class($model).'.'.get_class($related);
        if (isset($this->populatedJoins[$populatedKey])) {
            return $this->populatedJoins[$populatedKey];
        }
        
        $related_attributes = [];
        foreach ($attributes as $key => $value) {
            if (str_starts_with($key, $related->getTable() . '_')) {
                $related_attributes[Str::remove($related->getTable() . '_', $key)] = $value;
                unset($attributes[$key]); // Remove relation attributes from the query results
            }
        }
        
        // No relation results, init the relation with default attributes if defined
        if ($related_attributes[$related->getKeyName()] === null) {
            $relation->initRelation([$model], $name);
        } else {
            $model->setRelation($name, $related->setRawAttributes($related_attributes, true));
        }

        $this->populatedJoins[$populatedKey] = $related;
        return $related;
    }

    /**
     * @param array $columns
     * @param string $table
     * @param $as column alias prefix
     * @return array
     */
    protected function applyQualifiedColumnName(array $columns, string $table, $as = '')
    {
        return array_map(function($column) use ($table, $as) {
            if (strpos($column, '.') === false) {
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
        // TO DO: cache table columns using a cache driver..
        if (!isset(static::$cachedColumns[$table])) {
            static::$cachedColumns[$table] = $this->getConnection()->getSchemaBuilder()->getColumnListing($table);
        }
        return static::$cachedColumns[$table];
    }
}
