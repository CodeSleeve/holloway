<?php

namespace Holloway;

use Throwable;
use UnexpectedValueException;

final class Holloway
{
    /**
     * @var self
     */
    private static $instance;

    /**
     * The registered entity mappers, keyed by entity class name.
     *
     * @var array
     */
    private $mappers = [];

    /**
     * @return void
     */
    private function __construct()
    {
        //
    }

    /**
     * @return self
     */
    public static function instance() : self
    {
        if (!static::$instance) {
            static::$instance = new self;
        }

        return static::$instance;
    }

    /**
     * @param  mixed
     * @return void
     */
    public function register($mapperClasses)
    {
        if (!is_array($mapperClasses)) {
            $mapperClasses = func_get_args();
        }

        $mappers = array_map(function($mapperClass) {
            return new $mapperClass;
        }, $mapperClasses);

        $entityNames = array_map(function($mapper) {
            return $mapper->getEntityClassName();
        }, $mappers);

        $this->mappers = array_combine($entityNames, $mappers);

        foreach($this->mappers as $mapper) {
            $mapper->defineRelations();
        }
    }

    /**
     * @param array $mappers
     */
    public function setMappers(array $mappers)
    {
        $this->mappers = $mappers;
    }

    /**
     * @return array
     */
    public function getMappers() : array
    {
        return $this->mappers;
    }

    /**
     * @param  string|object $entityName
     * @throws UnexpectedValueException
     * @return Mapper
     */
    public function getMapper($entityName) : Mapper
    {
        if (is_object($entityName)) {
            $entityName = get_class($entityName);
        }

        try {
            return $this->mappers[$entityName];
        } catch (Throwable $e) {
            throw new UnexpectedValueException("Unknown entity $entityName, are you sure you've registered a map for this entity?", 1);
        }
    }

    /**
     * @param  string|object  $entityName
     * @return boolean
     */
    public function hasMapperFor($entityName) : bool
    {
        if (is_object($entityName)) {
            $entityName = get_class($entityName);
        }

        return array_key_exists($entityName, $this->mappers);
    }

    /**
     * @return void
     */
    public function flush()
    {
        $this->mappers = [];
    }

    /**
     * @return void
     */
    public function flushEntityCache()
    {
        foreach ($this->mappers as $mapper) {
            $mapper->flushEntityCache();
        }
    }
}