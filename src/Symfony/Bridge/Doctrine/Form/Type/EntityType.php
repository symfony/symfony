<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\Type;

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\QueryBuilder;

class EntityType extends DoctrineType
{
    /**
     * @var array
     */
    private $loaderCache = array();

    /**
     * Return the default loader object.
     *
     * @param ObjectManager         $manager
     * @param QueryBuilder|\Closure $queryBuilder
     * @param string                $class
     *
     * @return ORMQueryBuilderLoader
     *
     * @throws UnexpectedTypeException If the passed $queryBuilder is no \Closure
     *                                 and no QueryBuilder or if the closure
     *                                 does not return a QueryBuilder.
     */
    public function getLoader(ObjectManager $manager, $queryBuilder, $class)
    {
        if ($queryBuilder instanceof \Closure) {
            $queryBuilder = $queryBuilder($manager->getRepository($class));

            if (!$queryBuilder instanceof QueryBuilder) {
                throw new UnexpectedTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder');
            }
        } elseif (!$queryBuilder instanceof QueryBuilder) {
            throw new UnexpectedTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder or \Closure');
        }

        // It is important to return the same loader for identical queries,
        // otherwise the caching mechanism in DoctrineType does not work
        // (which expects identical loaders for the cache to work)
        $hash = md5($queryBuilder->getQuery()->getDQL());

        if (!isset($this->loaderCache[$hash])) {
            $this->loaderCache[$hash] = new ORMQueryBuilderLoader($queryBuilder);
        }

        return $this->loaderCache[$hash];
    }

    public function getName()
    {
        return 'entity';
    }
}
