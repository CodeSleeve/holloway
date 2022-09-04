<?php

namespace CodeSleeve\Holloway;

use Carbon\Carbon;
use CodeSleeve\Holloway\Scope;

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
     */
    public function apply(Builder $builder, Mapper $mapper) : void
    {
        $builder->whereNull($mapper->getQualifiedDeletedAtColumn());
    }

    /**
     * Extend the query builder with the needed functions.
     */
    public function extend(Builder $builder) : void
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
     */
    protected function getDeletedAtColumn(Builder $builder) : string
    {
        if (count((array) $builder->getQuery()->joins) > 0) {
            return $builder->getMapper()->getQualifiedDeletedAtColumn();
        }

        return $builder->getMapper()->getDeletedAtColumn();
    }

    /**
     * Add the restore extension to the builder.
     */
    protected function addRestore(Builder $builder) : void
    {
        $builder->macro('restore', function (Builder $builder) {
            $builder->withTrashed();

            return $builder->update([$builder->getMapper()->getDeletedAtColumn() => null]);
        });
    }

    /**
     * Add the with-trashed extension to the builder.
     */
    protected function addWithTrashed(Builder $builder) : void
    {
        $builder->macro('withTrashed', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * Add the without-trashed extension to the builder.
     */
    protected function addWithoutTrashed(Builder $builder) : void
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
     */
    protected function addOnlyTrashed(Builder $builder) : void
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
