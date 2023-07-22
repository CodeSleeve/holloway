<?php

namespace CodeSleeve\Holloway\Relationships;

use Closure;
use stdClass;
use Illuminate\Support\Collection;

class BelongsToMany extends BaseRelationship
{
    protected string $name;
    protected string $table;
    protected string $foreignKeyName;
    protected string $localKeyName;
    protected string $entityName;
    protected string $pivotTable;
    protected string $pivotForeignKeyName;
    protected string $pivotLocalKeyName;
    protected Collection $pivotData;

    public function __construct(
        string $name,
        string $table,
        string $foreignKeyName,
        string $localKeyName,
        string $entityName,
        string $pivotTable,
        string $pivotForeignKeyName,
        string $pivotLocalKeyName,
        Closure $query)
    {
       parent::__construct($name, $table, $foreignKeyName, $localKeyName, $entityName, $query);

       $this->pivotTable = $pivotTable;
       $this->pivotLocalKeyName = $pivotLocalKeyName;
       $this->pivotForeignKeyName = $pivotForeignKeyName;
    }

    /**
     * Load the data for a belongs to many relationship:
     *
     * 1. Use the information on the relationship to fetch the correct table,
     * using the local and foreign key names supplied in the relationship definition.
     *
     * 2. We'll constrian the results using the collection of records that the relationship
     * is being loaded onto.
     *
     * 3. Finally, we'll apply any contraints (if any) that were defined on the load and
     * return the fetched records.
     *
     * @param  Collection    $records
     * @param  Closure|null  $constraints
     * @return void
     */
    public function load(Collection $records, ?Closure $constraints = null) : void
    {
        $constraints = $constraints ?: function() {};

        $this->pivotData = ($this->query)()
             ->toBase()
             ->newQuery()
             ->from($this->pivotTable)
             ->whereIn("{$this->pivotTable}.{$this->pivotLocalKeyName}", $records->pluck($this->localKeyName)->all())
             ->get();

        $query = ($this->query)();

        $constraints($query);           // Allow for constraints to be applied to the Holloway\Builder $query

        $this->data = $query->toBase()
            ->from($this->table)
            ->whereIn("{$this->table}.{$this->foreignKeyName}", $this->pivotData->pluck($this->pivotForeignKeyName)->all())
            ->get();
    }

    /**
     * Generate the related related entities for a given record.
     *
     * 1. Filter the loaded data for this relationship to include the record that should be
     * loaded onto the related record.
     *
     * 2. Map that records into an entity instance.
     *
     * 3. Since this is a BelongsTo relationship, we'll return the first record from the mapped results.
     *
     * @param  stdClass $record
     * @return Collection
     */
    public function for(stdClass $record) : Collection
    {
        $pivotRecords = $this->pivotData
            ->filter(fn($pivotRecord) => $pivotRecord->{$this->pivotLocalKeyName} === $record->{$this->localKeyName})
            ->pluck($this->pivotForeignKeyName)
            ->values()
            ->all();

        return $this->data
            ->filter(fn(stdClass $relatedRecord) => in_array($relatedRecord->{$this->foreignKeyName}, $pivotRecords))
            ->values();
    }
}