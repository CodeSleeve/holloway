<?php

namespace Holloway;

class EntityCache
{
    /**
     * @var array
     */
    protected $items = [];

    /**
     * @var string
     */
    protected $keyName;

    /**
     * @param string $keyName
     */
    public function __construct(string $keyName)
    {
        $this->keyName = $keyName;
    }

    /**
     * @param  string $identifier
     * @return array|null
     */
    public function get(string $identifier) : ?array
    {
        return $this->items[$identifier] ?? null;
    }

    /**
     * @return array
     */
    public function all() : array
    {
        return $this->items;
    }

    /**
     * @param string $identifier
     * @return bool
     */
    public function has(string $identifier) : bool
    {
        return array_key_exists($identifier, $this->items);
    }

    /**
     * @param string $identifier
     * @param array  $attributes
     */
    public function add(string $identifier, array $attributes) : void
    {
        $this->items[$identifier] = $attributes;
    }

    /**
     * @param  array $records
     * @return void
     */
    public function merge(array $records) : void
    {
        foreach ($records as $record) {
            $identifier = $record[$this->keyName];

            $this->items[$identifier] = $record;
        }
    }

    /**
     * @return void
     */
    public function flush() : void
    {
        $this->items = [];
    }
}