<?php

namespace Holloway;

use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory as EloquentFactory;

class Factory extends EloquentFactory
{
    /**
     * @var Holloway
     */
    protected $manager;

    /**
     * Create a new factory instance.
     *
     * @param  Faker     $faker
     * @param  Holloway  $manager
     *
     * @return void
     */
    public function __construct(Faker $faker, Holloway $manager)
    {
        $this->manager = $manager;

        return parent::__construct($faker);
    }

    /**
     * Create a new factory container.
     *
     * @param  Faker        $faker
     * @param  string|null  $pathToFactories
     * @param  Holloway     $manager
     * @return static
     */
    public static function construct(Faker $faker, $pathToFactories = null, Holloway $manager = null)
    {
        $pathToFactories = $pathToFactories ?: database_path('factories');

        return (new static($faker, $manager))->load($pathToFactories);
    }

    /**
     * Create a builder for the given entity.
     *
     * @param  string  $class
     * @param  string  $name
     * @return FactoryBuilder
     */
    public function of($class, $name = 'default')
    {
        $mapper = $this->manager->getMapper($class);

        return new FactoryBuilder($mapper, $class, $name, $this->definitions, $this->states, $this->afterMaking, $this->afterCreating, $this->faker);
    }

}