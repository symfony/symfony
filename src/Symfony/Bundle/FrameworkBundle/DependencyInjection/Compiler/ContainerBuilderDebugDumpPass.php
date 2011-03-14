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
use Symfony\Component\DependencyInjection\Reference;

/**
 * Dumps the ContainerBuilder to a cache file so that it can be used by
 * debugging tools such as the container:debug console command.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 */
class ContainerBuilderDebugDumpPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $file = self::getBuilderCacheFilename($container);

        @mkdir(dirname($file), 0777, true);
        if (false !== @file_put_contents($file, serialize($container))) {
            chmod($file, 0666);
        } else {
            throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file));
        }
    }

    /**
     * Calculates the cache filename to be used to cache the ContainerBuilder
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @return string
     */
    public static function getBuilderCacheFilename(ContainerInterface $container)
    {
        $cacheDir = $container->getParameter('kernel.cache_dir');
        $name = $container->getParameter('kernel.name');
        $env = ucfirst($container->getParameter('kernel.environment'));
        $debug = ($container->getParameter('kernel.debug')) ? 'Debug' : '';

        return $cacheDir.'/'.$name.$env.$debug.'ProjectContainerBuilder.cache';
    }
}