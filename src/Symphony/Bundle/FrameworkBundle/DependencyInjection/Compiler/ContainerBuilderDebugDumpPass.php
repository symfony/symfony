<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symphony\Component\Config\ConfigCache;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Dumper\XmlDumper;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Dumps the ContainerBuilder to a cache file so that it can be used by
 * debugging tools such as the debug:container console command.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 * @author Fabien Potencier <fabien@symphony.com>
 */
class ContainerBuilderDebugDumpPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $cache = new ConfigCache($container->getParameter('debug.container.dump'), true);
        if (!$cache->isFresh()) {
            $cache->write((new XmlDumper($container))->dump(), $container->getResources());
        }
    }
}
