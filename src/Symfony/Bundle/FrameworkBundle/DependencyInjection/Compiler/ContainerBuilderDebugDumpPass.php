<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\Config\ConfigCache;

/**
 * Dumps the ContainerBuilder to a cache file so that it can be used by
 * debugging tools such as the container:debug console command.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ContainerBuilderDebugDumpPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $cache = new ConfigCache(self::getBuilderCacheFilename($container), false);

        $cache->write(serialize($container));
    }

    /**
     * Calculates the cache filename to be used to cache the ContainerBuilder
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *
     * @return string
     */
    static public function getBuilderCacheFilename(ContainerInterface $container)
    {
        $class = $container->getParameter('kernel.container_class');

        return $container->getParameter('kernel.cache_dir').'/'.$class.'Builder.cache';
    }
}
