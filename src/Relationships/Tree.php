<?php

namespace CodeSleeve\Holloway\Relationships;

use Illuminate\Support\Collection;
use CodeSleeve\Holloway\Mapper;
use CodeSleeve\Holloway\Holloway;
use CodeSleeve\Holloway\Exceptions\CircularDefaultLoadsException;

final class Tree
{
    protected Holloway $holloway;
    protected array $loads = [];
    protected array $data = [];
    protected Mapper $rootMapper;

    public function __construct(Mapper $rootMapper)
    {
        $this->holloway = Holloway::instance();
        $this->rootMapper = $rootMapper;
    }

    public function addLoads($loads) : self
    {
        $loads = is_array($loads) ? $loads : func_get_args();
        $loads = $this->normalize($loads);
        $this->loads = [...$this->loads, ...$loads];

        return $this;
    }

    public function removeLoads($loads) : self
    {
        $loads = is_array($loads) ? $loads : func_get_args();
        $this->loads = array_diff_key($this->loads, array_flip($loads));

        return $this;
    }

    public function getLoads() : array
    {
        return $this->loads;
    }

    public function getData() : array
    {
        return $this->data;
    }

    public function setData(array $data) : void
    {
        $this->data = $data;
    }

    /**
     * Load related entities from this tree onto the given collection of records:
     *
     * 1. Traverse the tree and load data into the relationship on each node.
     * 2. Traverse the tree again and make entities for each node.
     */
    public function loadInto(Collection $records) : Collection
    {
       $this->initialize();
       $this->loadData($this->data, $records);         // 1

       return $this->mapData($records, $this->data);   // 2
    }

    /**
     * If this tree doesn't currently contain any data then we'll call the buildTree()
     * method in order to pre-populate the nodes of the tree with our nested relations.
     */
    public function initialize() : void
    {
       if (!$this->data) {
           $this->data = $this->buildTree();
       }
    }

    /**
     * Traverse each of the tree nodes (each node is a relationship)
     * and load related data for it.
     */
    protected function loadData(array $nodes, Collection $records)
    {
        $stack = [];
        $stack[] = [$nodes, $records];

        while ($stack) {
            [$nodes, $records] = array_shift($stack);

            if ($records->count() > 0) {
                foreach($nodes as $nodeName => $node) {
                    $node['relationship']->load($records, $node['constraints']);

                    if ($node['children']) {
                        $stack[] = [$node['children'], $node['relationship']->getData()];
                    }
                }
            }
        }
    }

    /**
     * Traverse the loaded tree, map date from each node into an entity, attach those
     * entities onto their parent data so that they may then be used in the hydrate
     * method of the parent entity's map.
     *
     * @param  Collection $records  The records being mapped
     * @param  array      $data     A nested array of relationship data.
     * @return Collection
     */
    protected function mapData(Collection $records, array $data) : Collection
    {
        return $records->map(function($record) use ($data) {

            $record->relations = [];

            foreach($data as $relationshipName => $node) {
               $relationship = $node['relationship'];
               $relatedRecords = $relationship->for($record);

               if ($relationship instanceof HasOne || $relationship instanceof BelongsTo) {
                   if (!$relatedRecords) {
                       $relatedRecords = collect();
                   } else {
                       $relatedRecords = collect([$relatedRecords]);
                   }
               }

               // If we still have child relations left to map then we'll recurse.
               if ($node['children']) {
                   $relatedRecords = $this->mapData($relatedRecords, $node['children']);
               }

               // Now that there are no child relations left to map, we'll need to get the mapper for the related records
               // and map them into entities. However, if the relationship is a custom one, we'll skip the mapping step
               // and just store the raw records as the relation since custom relationships don't have a mapper.
               if ($relationship->getEntityName()) {
                   $mapper = Holloway::instance()->getMapper($relationship->getEntityName());

                   // Next, map them into entities.
                   if ($relationship instanceof HasOne || $relationship instanceof BelongsTo || ($relationship instanceof Custom && $relationship->shouldLimitToOne())) {
                       $relatedRecords = $relatedRecords->first();

                       if ($relatedRecords) {
                           $relatedRecords = $mapper->makeEntity($relatedRecords);
                       }
                   } else {
                       $relatedRecords = $relatedRecords->map(function($record) use ($mapper) {
                           return $mapper->makeEntity($record);
                       });
                   }
               } else {
                   // This is a custom relationship and we need to invoke its map() method.
                   $relatedRecords = $relatedRecords->map($relationship->getMap());

                   if ($relationship->shouldLimitToOne()) {
                       $relatedRecords = $relatedRecords->first();
                   }
               }

               // Finally, store them into the relations array under the name (of the relationship) that were defined under.
               $record->relations[$relationshipName] = $relatedRecords;
            }

            return $record;
        });
    }

    /**
     * Parse a list of relations into individuals.
     *
     * @param  array  $relations
     * @return array
     */
    protected function normalize(array $relations) : array
    {
        // First, we'll spin through each of the relations and map them to an array of key => value
        // pairs (loads) where the key is the name of the relation and the value is a closure.
        return collect($relations)->flatMap(function($constraints, $name) {

            // If the name of the relation being loaded is numeric, then we know that no constraints have
            // been set on the query that loads the relationship so we'll go ahead and normalize
            // the relation by assigning an empty clsure as the constraints for the query.
            if (is_numeric($name)) {
                [$name, $constraints] = [$constraints, function() {}];
            }

            return [$name => $constraints];
        })
        ->all();
    }

    /**
     * Given an array of relationship loads of the form:
     *
     * <code>
     *     [
     *          'parentRelation.childRelation.firstGrandchildRelation', 
     *          'parentRelation.childRelation.secondGrandchildRelation',
     *          'parentRelation.childRelation.thirdGrandchildRelation' => function($query) {
     *              $query->where('foo', 'bar');
     *          },
     *     ]
     * </code>
     *
     * convert it into an array (tree) of the form:
     *
     * <code>
     *     [
     *         'parentRelation' => [
     *             'name'         => 'parentRelation',
     *             'constraints'  => null,
     *             'relationship' => <Relationship> $relationship,
     *             'children'     => [
     *                 'childRelation' => [
     *                     'name'         => 'childRelation',
     *                     'constraints'  => null,
     *                     'relationship' => <Relationship> $relationship,
     *                     'children'     => [
     *                         'firstGrandchildRelation' => [
     *                             'name'         => 'firstGrandchildRelation',
     *                             'constraints'  => function() {},
     *                             'relationship' => <Relationship> $relationship,
     *                             'children'     => null
     *                         ],
     *
     *                         'secondGrandchildRelation' => [
     *                             'name'         => 'secondGrandchildRelation',
     *                             'constraints'  => function() {},
     *                             'relationship' => <Relationship> $relationship,
     *                             'children'     => null
     *                         ],
     * 
        *                      'thirdGrandchildRelation' => [
        *                          'name'         => 'thirdGrandchildRelation',
        *                          'constraints'  => function($query) {
        *                             $query->where('foo', 'bar');
        *                          },
        *                          'relationship' => <Relationship> $relationship,
        *                          'children'     => null,
        *                      ],
     *                     ]
     *                 ]
     *             ]
     *         ]
     *     ]
     * </code>
     *
     * Loads that share a parent are merged into the same node, whatever order they're given in. A new node also
     * gets the default loads ($with) of the mapper it leads to, but a load that was asked for always wins.
     */
    protected function buildTree()
    {
        /** @var array<string, array> $tree */
        $tree = [];

        foreach ($this->loads as $name => $constraints) {
            // 1. Start with the load itself. Only the default loads we add in step 7 carry a mapper chain (a load
            //    that was asked for has none), so the chain is what tells the two apart.
            $stack = [[&$tree, $name, $this->rootMapper, $constraints, null]];

            while ($stack) {
                $stackFrame = array_shift($stack);
                [&$subTree, $name, $mapper, $constraints, $mapperChain] = $stackFrame;

                // 2. Split the load at its first dot. For 'pups.collar', 'pups' is the node to build here and
                //    'collar' is the rest of the path to build beneath it.
                [$nodeName, $remainingPath] = array_pad(explode('.', $name, 2), 2, null);
                $isDefaultLoad = $mapperChain !== null;

                // 3. A default load never takes the place of a node that already has constraints, so that a load
                //    that was asked for always wins no matter what order the loads were given in.
                if ($isDefaultLoad && $remainingPath === null && isset($subTree[$nodeName]['constraints'])) {
                    continue;
                }

                // 4. Build the node if we haven't seen it yet. Its constraints stay null until a load for the node
                //    itself (or a default) gives it some, since a node we only pass through has none of its own.
                $isNewNode = !isset($subTree[$nodeName]);

                if ($isNewNode) {
                    $subTree[$nodeName] = [
                        'name'         => $nodeName,
                        'constraints'  => null,
                        'relationship' => clone $mapper->getRelationship($nodeName),
                        'children'     => []
                    ];
                }

                // 5. The last segment of a load is the node it names, so it gets the load's constraints. Setting
                //    them leaves any children the node already has alone.
                if ($remainingPath === null) {
                    $subTree[$nodeName]['constraints'] = $constraints;
                }

                $relationship = $subTree[$nodeName]['relationship'];

                // 6. If the load goes deeper and the relationship isn't a custom one (e.g customMany, etc) we'll
                //    push the rest of the path onto the stack to be built beneath this node.
                if ($remainingPath !== null && $relationship->getEntityName()) {
                    $stack[] = [
                        &$subTree[$nodeName]['children'],
                        $remainingPath,
                        $this->holloway->getMapper($relationship->getEntityName()),
                        $constraints,
                        $mapperChain,
                    ];
                }

                // 7. Add the default loads of the mapper that a new node leads to beneath it. Adding that mapper to
                //    the chain first lets us throw if its defaults lead back around to it instead of never stopping.
                //    Custom relationships load their records with their own query, so they don't get any.
                if ($isNewNode && !$relationship instanceof Custom) {
                    $relatedMapper = $this->holloway->getMapper($relationship->getEntityName());
                    $mapperChain = $this->extendMapperChain($mapperChain, $relatedMapper);

                    foreach ($this->normalize($relatedMapper->getWith()) as $defaultName => $defaultConstraints) {
                        $stack[] = [
                            &$subTree[$nodeName]['children'],
                            $defaultName,
                            $relatedMapper,
                            $defaultConstraints,
                            $mapperChain,
                        ];
                    }
                }
            }
        }

        return $tree;
    }

    /**
     * Add a mapper to the chain of mappers whose defaults led to a load, throwing if it's already in the chain.
     *
     * @param  array|null $mapperChain
     * @param  Mapper     $mapper
     * @return array
     */
    protected function extendMapperChain(?array $mapperChain, Mapper $mapper) : array
    {
        $mapperChain = $mapperChain ?? [];

        if (in_array($mapper, $mapperChain, true)) {
            $mapperNames = array_map(fn($mapper) => get_class($mapper), [...$mapperChain, $mapper]);

            throw new CircularDefaultLoadsException('The default loads of these mappers lead back to themselves: ' . implode(' -> ', $mapperNames) . '.');
        }

        return [...$mapperChain, $mapper];
    }
}