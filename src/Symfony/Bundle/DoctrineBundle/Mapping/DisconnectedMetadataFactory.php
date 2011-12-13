<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Mapping;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DisconnectedMetadataFactory extends MetadataFactory
{
    protected function getClassMetadataFactoryClass()
    {
        return 'Doctrine\\ORM\\Tools\\DisconnectedClassMetadataFactory';
    }
}
