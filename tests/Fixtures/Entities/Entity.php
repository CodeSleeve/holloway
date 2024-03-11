<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

use Illuminate\Support\Str;

abstract class Entity
{
    protected int|string|null $id = null;

    /**
     * Return an array representation of this entity.
     *
     * @return array
     */
    public function toArray() : array
    {
        return get_object_vars($this);
    }

    /**
     * @param  string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        $accessor = $this->attributeAccessorName($name);

        if (property_exists($this, $name)) {
            return $this->$name;
        } elseif (method_exists($this, $accessor)) {
            return $this->$accessor();
        } 
    }

    /**
     * @param  mixed  $name
     * @return boolean
     */
    public function __isset($name)
    {
        return property_exists($this, $name) || method_exists($this, $this->attributeAccessorName($name));
    }

    /**
     * @return array
     */
    public function jsonSerialize() : array
    {
        return $this->toArray();
    }

    /**
     * @return string
     */
    public function toJson() : string
    {
        return json_encode($this);
    }

    /**
     * FOR USE ONLY BY ENTITY MAPPERS to hydrate our entities
     *
     * @param  array  $properties
     * @return void
     */
    public function mapperFill(array $properties) : self
    {
        foreach($properties as $propertyName => $propertyValue) {
            $this->$propertyName = $propertyValue;
        }

        return $this;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param  string $name
     * @return string
     */
    private function attributeAccessorName(string $name) : string
    {
        return 'get' . Str::studly($name);
    }
}