<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection\Provider;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

/**
 * @author Victor Berchet <victor@suumit.com>
 */
interface ProviderFactoryInterface
{
    function getName();
    //function configure(ContainerBuilder $container, $name, array $config);
}