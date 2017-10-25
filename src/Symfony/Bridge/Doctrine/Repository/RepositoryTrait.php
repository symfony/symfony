<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;

/**
 * A helpful trait when creating your own repository.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
trait RepositoryTrait
{
    /**
     * @return EntityManagerInterface
     */
    abstract protected function getEntityManager();

    /**
     * @return string the class name for your entity
     */
    abstract protected function getClassName();

    /**
     * @see EntityRepository::createQueryBuilder()
     *
     * @param string $alias
     * @param string $indexBy the index for the from
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder($alias, $indexBy = null)
    {
        return $this->getRepository()->createQueryBuilder($alias, $indexBy);
    }

    /**
     * @see EntityRepository::createResultSetMappingBuilder()
     *
     * @param string $alias
     *
     * @return ResultSetMappingBuilder
     */
    public function createResultSetMappingBuilder($alias)
    {
        return $this->getRepository()->createResultSetMappingBuilder($alias);
    }

    /**
     * @see EntityRepository::createNamedQuery()
     *
     * @param string $queryName
     *
     * @return Query
     */
    public function createNamedQuery($queryName)
    {
        return $this->getRepository()->createNamedQuery($queryName);
    }

    /**
     * @see EntityRepository::createNativeNamedQuery()
     *
     * @param string $queryName
     *
     * @return NativeQuery
     */
    public function createNativeNamedQuery($queryName)
    {
        return $this->getRepository()->createNativeNamedQuery($queryName);
    }

    /**
     * @see EntityRepository::clear()
     */
    public function clear()
    {
        $this->getRepository()->clear();
    }

    /**
     * @see EntityRepository::find()
     *
     * @param mixed    $id          the identifier
     * @param int|null $lockMode    one of the \Doctrine\DBAL\LockMode::* constants
     *                              or NULL if no specific lock mode should be used
     *                              during the search
     * @param int|null $lockVersion the lock version
     *
     * @return object|null the entity instance or NULL if the entity can not be found
     */
    public function find($id, $lockMode = null, $lockVersion = null)
    {
        return $this->getRepository()->find($id, $lockMode, $lockVersion);
    }

    /**
     * @see EntityRepository::findAll()
     *
     * @return array the entities
     */
    public function findAll()
    {
        return $this->getRepository()->findAll();
    }

    /**
     * @see EntityRepository::findBy()
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return array the objects
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->getRepository()->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @see EntityRepository::findOneBy()
     *
     * @param array      $criteria
     * @param array|null $orderBy
     *
     * @return object|null the entity instance or NULL if the entity can not be found
     */
    public function findOneBy(array $criteria, array $orderBy = null)
    {
        return $this->findBy($criteria, $orderBy);
    }

    /**
     * @see EntityRepository::matching()
     *
     * @param \Doctrine\Common\Collections\Criteria $criteria
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function matching(Criteria $criteria)
    {
        return $this->getRepository()->matching($criteria);
    }

    /**
     * @return EntityRepository
     */
    private function getRepository()
    {
        return $this->getEntityManager()->getRepository($this->getClassName());
    }
}
