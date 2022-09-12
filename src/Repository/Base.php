<?php

namespace App\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\PersistentCollection;
use Doctrine\DBAL\Connection;
use App\Entity\Base as Entity;

/**
 * Represents a base Repository.
 */
abstract class Base
{
    const ID_SEPARATOR = '~';

    /**
     * @return string
     */
    abstract public function getRepositoryName();

    /**
     * @var Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var EntityRepository
     */
    protected $repository;

    /**
     * @return string
     */
    protected $class;

    /**
     * @param Doctrine\ORM\EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $class = $this->getRepositoryName();

        $this->em = $em;
        $this->repository = $em->getRepository($class);
        $this->class = $em->getClassMetadata($class)->name;

        $config = $em->getConfiguration();
        $config->addCustomDatetimeFunction('DATE_FORMAT', 'App\Doctrine\ORM\Query\AST\Functions\DateFormatFunction');
        $config->addCustomDatetimeFunction('UNIX_TIMESTAMP', 'App\Doctrine\ORM\Query\AST\Functions\UnixTimestampFunction');
        $config->addCustomDatetimeFunction('CONVERT_TZ', 'App\Doctrine\ORM\Query\AST\Functions\ConvertTzFunction');
        $config->addCustomStringFunction('REPLACE', 'App\Doctrine\ORM\Query\AST\Functions\ReplaceFunction');
        $config->addCustomStringFunction('SHA1', 'App\Doctrine\ORM\Query\AST\Functions\Sha1Function');
        $config->addCustomStringFunction('ACOS', 'App\Doctrine\ORM\Query\AST\Functions\AcosFunction');
        $config->addCustomStringFunction('COS', 'App\Doctrine\ORM\Query\AST\Functions\CosFunction');
        $config->addCustomStringFunction('SIN', 'App\Doctrine\ORM\Query\AST\Functions\SinFunction');
        $config->addCustomStringFunction('RADIANS', 'App\Doctrine\ORM\Query\AST\Functions\RadiansFunction');
    }

    /**
     * Gets a named entity manager.
     *
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * Gets the repository for an entity class.
     *
     * @return EntityRepository The repository class.
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @param Entity $entity
     *
     * @return void
     */
    public function create(Entity $entity)
    {
        if (method_exists($entity, 'prePersist')) {
            $entity->prePersist();
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * @param Entity $entity
     *
     * @return void
     */
    public function update(Entity $entity)
    {
        if (method_exists($entity, 'preUpdate')) {
            $entity->preUpdate();
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * @param Entity $entity
     *
     * @return void
     */
    public function delete(Entity $entity)
    {
        $this->em->remove($entity);
        $this->em->flush();
    }

    /**
     * @param string $class
     * @param mixed  $id
     *
     * @return object the object with id or null if not found
     */
    public function find($id)
    {
        if (!isset($id)) {
            return null;
        }

        $values = array_combine($this->em->getMetadataFactory()->getMetadataFor($this->class)->getIdentifierFieldNames(), explode(self::ID_SEPARATOR, $id));

        return $this->getRepository()->find($values);
    }

    /**
     * @param string $class
     * @param array  $criteria
     *
     * @return array all objects matching the criteria
     */
    public function findBy(array $criteria = array())
    {
        return $this->getRepository()->findBy($criteria);
    }

    /**
     * @param string $class
     * @param array  $criteria
     *
     * @return object an object matching the criteria or null if none match
     */
    public function findOneBy(array $criteria = array())
    {
        return $this->getRepository()->findOneBy($criteria);
    }

    /**
     * @param QueryBuilder $query   A QueryBuilder instance (required)
     * @param mixed        $alias   The entity alias (required)
     * @param array        $filters Filters (optional)
     * @param integer      $page    Pagination offset (optional)
     * @param integer      $limit   Maximum number of results to retrieve (optional)
     */
    public function doSearch(QueryBuilder $query, $alias, array $filters = array(), $page = 1, $limit = 20)
    {
        // return one
        if (isset($filters['one']) && $filters['one']) {
            try {
                return $query->getQuery()->getSingleResult();
            } catch (NoResultException $error) {
                return null;
            }

        }

        // return all
        if (isset($filters['all']) && $filters['all']) {
            $result = $query->getQuery()->execute();

            if (isset($filters['select']) && $filters['select']) {
                if (!is_array($filters['select'])) {
                    $filters['select'] = array($filters['select']);
                }

                $data = array();

                foreach ($result as $key => $value) {
                    foreach ($filters['select'] as $field) {
                        $data[$key][$field] = $value->__get($field);
                    }
                }

                return $data;
            }

            return $result;
        }

        $queryClone = clone($query);

        $total = $queryClone
                   ->select(sprintf('count(%s) as total', $alias))
                   ->resetDQLPart('orderBy')
                   ->getQuery()
                   ->getSingleScalarResult();

        // return count
        if (isset($filters['count']) && $filters['count']) {
            return (int) $total;
        }

        $offset = -1;
        if ($limit > -1) {
            $query
                ->setFirstResult($offset = ($page-1)*$limit)
                ->setMaxResults($limit)
            ;
        }

        $result = $query->getQuery()->execute();

        return array(
            'info' => array(
                'offset' => $offset,
                'limit'  => $limit,
                'count'  => $total,
            ),
            'count'  => count($result),
            'result' => $result,
        );
    }
}
