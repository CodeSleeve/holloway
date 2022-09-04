<?php

namespace CodeSleeve\Holloway;

class EntityCache
{
    public function __construct(
        protected string $keyName, 
        protected array $items = [],
    ) {
        //
    }

    public function get(string $identifier) : ?array
    {
        return $this->items[$identifier] ?? null;
    }

    public function all() : array
    {
        return $this->items;
    }

    public function count() : int
    {
        return count($this->items);
    }

    public function has(string $identifier) : bool
    {
        return array_key_exists($identifier, $this->items);
    }

    public function set(string $identifier, array $attributes) : void
    {
        $this->items[$identifier] = $attributes;
    }

    public function merge(array $records) : void
    {
        foreach ($records as $record) {
            $identifier = $record[$this->keyName];

            $this->items[$identifier] = $record;
        }
    }

    public function remove(string $identifier) : void
    {
        unset($this->items[$identifier]);
    }

    public function flush() : void
    {
        $this->items = [];
    }
}