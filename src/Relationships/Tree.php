<?php

namespace CodeSleeve\Holloway\Relationships;

use Illuminate\Support\Collection;
use CodeSleeve\Holloway\Functions\Str;
use CodeSleeve\Holloway\Mapper;
use CodeSleeve\Holloway\Holloway;

final class Tree
{
    /** @var Holloway */
    protected $holloway;

    /** @var array */
    protected $loads = [];

    /** @var array */
    protected $data = [];

    /** @var Mapper */
    protected $rootMapper;

    /**
     * @param Mapper $rootMapper
     */
    public function __construct(Mapper $rootMapper)
    {
        $this->holloway = Holloway::instance();
        $this->rootMapper = $rootMapper;
    }

    /**
     * @param  mixed  $loads
     * @return $this
     */
    public function addLoads($loads)
    {
        $loads = is_array($loads) ? $loads : func_get_args();
        $loads = $this->normalize($loads);

        $this->loads = array_merge($this->loads, $loads);
    }

    /**
     * @param  mixed  $loads
     * @return $this
     */
    public function removeLoads($loads)
    {
        $loads = is_array($loads) ?: func_get_args();

        $this->loads = array_diff_key($this->loads, array_flip($loads));
    }

    /**
     * @return array
     */
    public function getLoads() : array
    {
        return $this->loads;
    }

    /**
     * @return array
     */
    public function getData() : array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Load related entities from this tree onto the given collection of records:
     *
     * 1. Traverse the tree and load data into the relationship on each node.
     * 2. Traverse the tree again and make entities for each node.
     *
     * @param  Collection $records
     * @return Collection
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
     *
     * @return void
     */
    public function initialize()
    {
       if (!$this->data) {
           $this->data = $this->buildTree();
       }
    }

    /**
     * Traverse each of the tree nodes (each node is a relationship)
     * and load related data for it.
     *
     * @param  array      $nodes
     * @param  Collection $records
     * @return void
     */
    protected function loadData(array $nodes, Collection $records)
    {
        foreach($nodes as $nodeName => $node) {
            $node['relationship']->load($records, $node['constraints']);

            if ($node['children']) {
                $this->loadData($node['children'], $node['relationship']->getData());
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
     *     ['parentRelation.childRelation.firstGrandchildRelation', 'parentRelation.childRelation.secondGrandchildRelation']
     * </code>
     *
     * convert it into an array (tree) of the form:
     *
     * <code>
     *     [
     *         'parentRelation' => [
     *             'name'         => 'parentRelation',
     *             'constraints'  => function() {},
     *             'relationship' => <Relationship> $relationship,
     *             'children'     => [
     *                 'childRelation' => [
     *                     'name'         => 'childRelation',
     *                     'constraints'  => function() {},
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
     *                     ]
     *                 ]
     *             ]
     *         ]
     *     ]
     * </code>
     *
     * @param  array  $loads
     * @param  Mapper $mapper
     * @param  array  $tree
     * @return array
     */
    protected function buildTree()
    {
        $tree = [];

        foreach ($this->loads as $name => $constraints) {
            $stack = [[&$tree, $name, $this->rootMapper, $constraints]];

            while ($stack) {
                $stackFrame = array_shift($stack);
                [&$subTree, $name, $mapper, $constraints] = $stackFrame;

                if (mb_strpos($name, '.') === false) {
                    $nodeName = $name;

                    $node = [
                        'name'         => $nodeName,
                        'constraints'  => $constraints,
                        'relationship' => $mapper->getRelationship($nodeName),
                        'children'     => []
                    ];

                    $subTree[$nodeName] = $node;
                } else {
                    $nodeName = explode('.', $name)[0];

                    if (!array_key_exists($nodeName, $subTree)) {
                        $node = [
                            'name'         => $nodeName,
                            'constraints'  => $constraints,
                            'relationship' => $mapper->getRelationship($nodeName),
                            'children'     => []
                        ];

                        $subTree[$nodeName] = $node;
                    } else {
                        $node = $subTree[$nodeName];
                    }

                    // If the relationship isn't a custom one (e.g customMany, etc) we'll push it onto the stack
                    if ($node['relationship']->getEntityName()) {
                        $stack[] = [
                            &$subTree[$nodeName]['children'],
                            str_replace("$nodeName.", '', $name),
                            $this->holloway->getMapper($node['relationship']->getEntityName()),
                            function() {}
                        ];
                    }
                }
            }
        }

        return $tree;
    }
}