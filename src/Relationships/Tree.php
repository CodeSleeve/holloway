<?php

namespace Holloway\Relationships;

use Illuminate\Support\Collection;
use Holloway\Functions\Str;
use Holloway\Mapper;
use Holloway\Holloway;

final class Tree
{
    protected $holloway;
    protected $loads = [];
    protected $data = [];
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
     * @param array $tree
     */
    public function setTree(array $tree)
    {
        $this->tree = $tree;
    }

    /**
     * @param array $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function render() : array
    {
        if (!$this->data) {
            $this->data = $this->buildTree($this->loads, $this->rootMapper);
        }

        return $this->data;
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
       $this->loadData($this->render(), $records);     // 1

       return $this->mapData($records, $this->data);   // 2
    }

    /**
     * Traverse the tree and load related data for each node.
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
     * @param  Collection $records
     * @return Collection
     */
    protected function mapData(Collection $records, array $data) : Collection
    {
        return $records->map(function($record) use ($data) {

            $record->relations = [];

            foreach($data as $nodeName => $node) {
               $relationship = $node['relationship'];
               $relatedRecords = $relationship->for($record);
               $relatedRecords = $relatedRecords instanceof Collection ? $relatedRecords : collect([$relatedRecords]);

               if ($node['children']) {
                   $record->relations[$nodeName] = $this->mapData($relatedRecords, $node['children']);
               }

               $mapper = Holloway::instance()->getMapper($relationship->getEntityName());

               if ($relationship instanceof HasOne || $relationship instanceof BelongsTo) {
                   $relatedRecords = $mapper->makeEntity($relatedRecords->first());
               } else {
                   $relatedRecords = $relatedRecords->map(function($record) use ($mapper) {
                       return $mapper->makeEntity($record);
                   });
               }

               $record->relations[$nodeName] = $relatedRecords;
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
     * @return array
     */
    protected function buildTree(array $loads, Mapper $mapper) : array
    {
        $tree = [];

        foreach ($loads as $name => $constraints) {
            if (mb_strpos($name, '.') === false) {
                $nodeName = $name;

                $node = [
                    'name'         => $nodeName,
                    'constraints'  => $constraints,
                    'relationship' => $mapper->getRelationship($nodeName),
                    'children'     => []
                ];

                $tree[$nodeName] = $node;
            } else {
                $nodeName = explode('.', $name)[0];

                if (!array_key_exists($nodeName, $tree)) {
                    $node = [
                        'name'         => $nodeName,
                        'constraints'  => $constraints,
                        'relationship' => $mapper->getRelationship($nodeName),
                        'children'     => []
                    ];
                } else {
                    $node = $tree[$nodeName];
                }

                $childMapper = $this->holloway->getMapper($node['relationship']->getEntityName());
                $remainingLoads = [str_replace("$nodeName.", '', $name) => function() {}];

                $node['children'] = array_merge($node['children'], $this->buildTree($remainingLoads, $childMapper));

                $tree[$nodeName] = $node;
            }
        };

        return $tree;
    }
}