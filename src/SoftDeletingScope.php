<?php

namespace Holloway;

use Carbon\Carbon;
use Holloway\Scope;

class SoftDeletingScope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var array
     */
    protected $extensions = ['Restore', 'WithTrashed', 'WithoutTrashed', 'OnlyTrashed'];

    /**
     * Apply the scope to a given Mapper query builder.
     *
     * @param  Builder $builder
     * @param  Mapper  $mapper
     * @return void
     */
    public function apply(Builder $builder, Mapper $mapper)
    {
        $builder->whereNull($mapper->getQualifiedDeletedAtColumn());
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  Builder  $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }

        $builder->onDelete(function (Builder $builder) {
            $column = $this->getDeletedAtColumn($builder);

            return $builder->update([
                $column => Carbon::now()
            ]);
        });
    }

    /**
     * Get the "deleted at" column for the builder.
     * If we have joins on the query, we'll need to use the fully qualified name.
     *
     * @param  Builder  $builder
     * @return string
     */
    protected function getDeletedAtColumn(Builder $builder)
    {
        if (count((array) $builder->getQuery()->joins) > 0) {
            return $builder->getMapper()->getQualifiedDeletedAtColumn();
        }

        return $builder->getMapper()->getDeletedAtColumn();
    }

    /**
     * Add the restore extension to the builder.
     *
     * @param  Builder  $builder
     * @return void
     */
    protected function addRestore(Builder $builder)
    {
        $builder->macro('restore', function (Builder $builder) {
            $builder->withTrashed();

            return $builder->update([$builder->getMapper()->getDeletedAtColumn() => null]);
        });
    }

    /**
     * Add the with-trashed extension to the builder.
     *
     * @param  Builder  $builder
     * @return void
     */
    protected function addWithTrashed(Builder $builder)
    {
        $builder->macro('withTrashed', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @param  Builder  $builder
     * @return void
     */
    protected function addWithoutTrashed(Builder $builder)
    {
        $builder->macro('withoutTrashed', function (Builder $builder) {
            $mapper = $builder->getMapper();

            $builder->withoutGlobalScope($this)->whereNull(
                $mapper->getQualifiedDeletedAtColumn()
            );

            return $builder;
        });
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @param  Builder  $builder
     * @return void
     */
    protected function addOnlyTrashed(Builder $builder)
    {
        $builder->macro('onlyTrashed', function (Builder $builder) {
            $mapper = $builder->getMapper();

            $builder->withoutGlobalScope($this)->whereNotNull(
                $mapper->getQualifiedDeletedAtColumn()
            );

            return $builder;
        });
    }
}
