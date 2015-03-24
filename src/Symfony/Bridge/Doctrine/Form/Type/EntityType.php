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

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;

class EntityType extends DoctrineType
{
    /**
     * @var ORMQueryBuilderLoader[]
     */
    private $loaderCache = array();

    /**
     * Return the default loader object.
     *
     * @param ObjectManager $manager
     * @param mixed         $queryBuilder
     * @param string        $class
     *
     * @return ORMQueryBuilderLoader
     */
    public function getLoader(ObjectManager $manager, $queryBuilder, $class)
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            return new ORMQueryBuilderLoader(
                $queryBuilder,
                $manager,
                $class
            );
        }

        $queryBuilderHash = $this->getQueryBuilderHash($queryBuilder);
        $loaderHash = $this->getLoaderHash($manager, $queryBuilderHash, $class);

        if (!isset($this->loaderCache[$loaderHash])) {
            $this->loaderCache[$loaderHash] = new ORMQueryBuilderLoader(
                $queryBuilder,
                $manager,
                $class
            );
        }

        return $this->loaderCache[$loaderHash];
    }

    /**
     * @param QueryBuilder $queryBuilder
     *
     * @return string
     */
    private function getQueryBuilderHash(QueryBuilder $queryBuilder)
    {
        return hash('sha256', json_encode(array(
            'sql' => $queryBuilder->getQuery()->getSQL(),
            'parameters' => $queryBuilder->getParameters(),
        )));
    }

    /**
     * @param ObjectManager $manager
     * @param string        $queryBuilderHash
     * @param string        $class
     *
     * @return string
     */
    private function getLoaderHash(ObjectManager $manager, $queryBuilderHash, $class)
    {
        return hash('sha256', json_encode(array(
            'manager' => spl_object_hash($manager),
            'queryBuilder' => $queryBuilderHash,
            'class' => $class,
        )));
    }

    public function getName()
    {
        return 'entity';
    }
}
