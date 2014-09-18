<?php

namespace Opifer\CrudBundle\Datagrid;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManager;

use Opifer\CrudBundle\Annotation\GridAnnotationReader;
use Opifer\CrudBundle\Doctrine\EntityHelper;

use Opifer\RulesEngine\Environment\DoctrineEnvironment;

/**
 * @todo Find a better name for this class and maybe also devide the methods into
 * two separate classes, which makes more sense.
 */
class FilterBuilder
{
    protected $em;
    protected $entityHelper;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em, EntityHelper $entityHelper)
    {
        $this->em = $em;
        $this->entityHelper = $entityHelper;
    }

    /**
     * [build description]
     * @param  Opifer\RulesEngine\Condition $conditions
     * @param  string                       $entity
     * @return [type]
     */
    public function getRowQuery($conditions, $entity)
    {
       // var_dump($conditions);
       // var_dump($entity);
       // die();

        // waarom passen we condities niet gewoon op een repository toe? maak
        // de repository dat ie implements environment met de get operator en de
        // condities kunnen door middel van evaluate() rechtstreeks de Env aanpassen

        $repo = $this->em->getRepository(get_class($entity));
        $qb = $repo->createQueryBuilder('a'); // use exotic alias because we use entity's own repository

        $environment = new DoctrineEnvironment();
        $environment->queryBuilder = $qb;
        $environment->evaluate($conditions);

        return $qb;
    }

    /**
     * @param  array $items
     * @return array
     */
    public function wheres($items)
    {
        $wheres = [];
        foreach ($items as $key => $row) {
            if (array_key_exists('param', $row) && $row['param'] == 'limit') {
                // do nothing
                //$this->limit = $row['value'];
            } else {
                $param = explode('-', $row['param']);
                if (count($wheres == 0)) {
                    $wheres['where'] = ['a.' . $param[1], $row['comparator'], $row['values']];
                } else {
                    $wheres['orWhere'] = ['a.' . $param[1], $row['comparator'], $row['values']];
                }
            }
        }

        return $wheres;
    }

    /**
     * @param string $entity
     *
     * @todo   allow multiple joins from same join type
     *
     * @return array
     */
    public function joins($entity)
    {
        $joins = [];
        $i = 'b';
        foreach ($this->entityHelper->getRelations($entity) as $relation) {
            $joins['innerJoin'] = ['a.' . $relation['fieldName'], $i];
            $i++;
        }

        return $joins;
    }

    /**
     * Return all allowed fields related to the given entity
     *
     * It checks if the property is part of the allowedProperties, defined in
     * the entity annotations. If no annotations are defined, just show all
     * possible columns.
     *
     * @return array
     */
    public function allColumns($entity)
    {
        $columns = [];
        $annotationReader = new GridAnnotationReader();
        $allowedProperties = $annotationReader->all($entity);

        foreach ($this->entityHelper->getProperties($entity) as $column) {
            if (count($allowedProperties)) {
                foreach ($allowedProperties as $property) {
                    if ($column['fieldName'] != $property['property'])
                        continue;

                    if (!isset($property['type']))
                        $property['type'] = $column['fieldName'];

                    $columns[] = $property;
                }
            } else {
                $columns[] = [
                    'property' => $column['fieldName'],
                    'type' => $column['type']
                ];
            }
        }

        $relations = [];
        foreach ($this->entityHelper->getRelations($entity) as $relation) {
            if (count($allowedProperties)) {
                foreach ($allowedProperties as $property) {
                    if ($relation['fieldName'] != $property['property'])
                        continue;

                    // When the relation is a one-to-many or many-to-many relation,
                    // only return the relation-count.
                    // Obviously needs some refactoring
                    if (in_array($relation['type'], array(4,8))) {
                        $columns[] = [
                            'property' => $relation['fieldName'] . '.count',
                            'type' => $relation['type'] // This returns an integer, referencing the type of relation
                        ];
                        continue;
                    }

                    foreach ($this->entityHelper->getProperties($relation['targetEntity']) as $relationfield) {
                        $columns[] = [
                            'property' => $relation['fieldName'].'.'.$relationfield['fieldName'],
                            'type' => $relationfield['type']
                        ];
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * Filter rows by any searchterm
     *
     * @param  string       $entity
     * @param  string       $term
     * @return QueryBuilder
     */
    public function any($entity, $term)
    {
        $repository = $this->em->getRepository(get_class($entity));
        $qb = $repository->createQueryBuilder('a');

        $i = 0;
        foreach ($this->entityHelper->getProperties(get_class($entity)) as $column) {
            if ($i = 0) {
                $qb->where($qb->expr()->like('a.'. $column['fieldName'], ':query'));
            } else {
                $qb->orWhere($qb->expr()->like('a.'. $column['fieldName'], ':query'));
            }
            $i++;
        }
        $qb->setParameter('query', '%'.$term . '%');

        return $qb;
    }
}