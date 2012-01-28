<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\ChoiceList;

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Doctrine\ORM\QueryBuilder;

/**
 * Getting Entities through the ORM QueryBuilder
 */
class ORMQueryBuilderLoader implements EntityLoaderInterface
{
    /**
     * Contains the query builder that builds the query for fetching the
     * entities
     *
     * This property should only be accessed through queryBuilder.
     *
     * @var Doctrine\ORM\QueryBuilder
     */
    private $queryBuilder;

    /**
     * Construct an ORM Query Builder Loader
     *
     * @param QueryBuilder $queryBuilder
     * @param EntityManager $manager
     * @param string $class
     */
    public function __construct($queryBuilder, $manager = null, $class = null)
    {
        // If a query builder was passed, it must be a closure or QueryBuilder
        // instance
        if (!($queryBuilder instanceof QueryBuilder || $queryBuilder instanceof \Closure)) {
            throw new UnexpectedTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder or \Closure');
        }

        if ($queryBuilder instanceof \Closure) {
            $queryBuilder = $queryBuilder($manager->getRepository($class));

            if (!$queryBuilder instanceof QueryBuilder) {
                throw new UnexpectedTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder');
            }
        }

        $this->queryBuilder = $queryBuilder;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntities()
    {
        return $this->queryBuilder->getQuery()->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function getEntity($field, $value)
    {
        $alias = $this->queryBuilder->getRootAlias();
        $where = $this->queryBuilder->expr()->eq($alias.'.'.$field, ':getEntity'.$field);
        
        return $this->queryBuilder->andWhere($where)->setParameter('getEntity'.$field, $value)->getQuery()->getOneOrNullResult();
    }
}
