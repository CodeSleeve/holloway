<?php

namespace CodeSleeve\Holloway\Relationships;

use Closure;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use CodeSleeve\Holloway\Mapper;
use stdClass;

class BelongsToMany extends BaseRelationship
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var string
     */
    protected $foreignKeyName;

    /**
     * @var string
     */
    protected $localKeyName;

    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var string
     */
    protected $pivotTableName;

    /**
     * @var string
     */
    protected $pivotForeignKeyName;

    /**
     * @var string
     */
    protected $pivotLocalKeyName;

    /**
     * @var Collection
     */
    protected $pivotData;

    /**
     * @param string       $name
     * @param string       $tableName
     * @param string       $foreignKeyName
     * @param string       $localKeyName
     * @param string       $entityName
     * @param string       $pivotTableName
     * @param string       $pivotForeignKeyName
     * @param string       $pivotLocalKeyName
     * @param QueryBuilder $query
     */
    public function __construct(
        string $name,
        string $tableName,
        string $foreignKeyName,
        string $localKeyName,
        string $entityName,
        string $pivotTableName,
        string $pivotForeignKeyName,
        string $pivotLocalKeyName,
        QueryBuilder $query)
    {
       parent::__construct($name, $tableName, $foreignKeyName, $localKeyName, $entityName, $query);

       $this->pivotTableName = $pivotTableName;
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
    public function load(Collection $records, ?Closure $constraints = null)
    {
        $constraints = $constraints ?: function() {};

        $this->pivotData = $this->query
             ->newQuery()
             ->from($this->pivotTableName)
             ->whereIn($this->pivotLocalKeyName, $records->pluck($this->localKeyName)->all())
             ->get();

        $this->data = (clone $this->query)
            ->from($this->tableName)
            ->whereIn($this->foreignKeyName, $this->pivotData->pluck($this->pivotForeignKeyName)->all())
            ->where($constraints)
            ->get();

        return $this;
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
            ->filter(function($pivotRecord) use ($record) {
                return $pivotRecord->{$this->pivotLocalKeyName} === $record->{$this->localKeyName};
            })
            ->pluck($this->pivotForeignKeyName)
            ->values()
            ->all();

        return $this->data
            ->filter(function(stdClass $relatedRecord) use ($pivotRecords) {
                return in_array($relatedRecord->{$this->foreignKeyName}, $pivotRecords);
            })
            ->values();
    }
}