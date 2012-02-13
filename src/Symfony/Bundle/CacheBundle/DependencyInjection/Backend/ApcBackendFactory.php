<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection\Backend;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Victor Berchet <victor@suumit.com>
 */
class ApcBackendFactory extends AbstractBackendFactory
{
    public function addConfiguration(NodeBuilder $builder)
    {
        $builder->scalarNode($this->getConfigKey())->end();
    }

    public function createService($id, ContainerBuilder $container, $config)
    {
    }
}