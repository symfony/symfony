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
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;

/**
 * @since v2.1.0
 */
class EntityType extends DoctrineType
{
    /**
     * Return the default loader object.
     *
     * @param ObjectManager $manager
     * @param mixed         $queryBuilder
     * @param string        $class
     * @return ORMQueryBuilderLoader
     *
     * @since v2.1.0
     */
    public function getLoader(ObjectManager $manager, $queryBuilder, $class)
    {
        return new ORMQueryBuilderLoader(
            $queryBuilder,
            $manager,
            $class
        );
    }

    /**
     * @since v2.0.0
     */
    public function getName()
    {
        return 'entity';
    }
}
