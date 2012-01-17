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

class EntityType extends DoctrineType
{
    /**
     * Return the default loader object.
     *
     * @param ObjectManager $manager
     * @param array $options
     * @return ORMQueryBuilderLoader
     */
    protected function getLoader(ObjectManager $manager, array $options)
    {
        return new ORMQueryBuilderLoader(
            $options['query_builder'],
            $manager,
            $options['class']
        );
    }

    public function getName()
    {
        return 'entity';
    }
}
