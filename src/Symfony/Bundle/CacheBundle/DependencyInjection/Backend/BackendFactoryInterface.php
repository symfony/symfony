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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

/**
 * @author Victor Berchet <victor@suumit.com>
 */
interface BackendFactoryInterface
{
    function init(ContainerBuilder $container, $config);
    function getType();
    function getConfigKey();
    function addConfiguration(NodeBuilder $builder);
    function createService($id, ContainerBuilder $container, $config);
}