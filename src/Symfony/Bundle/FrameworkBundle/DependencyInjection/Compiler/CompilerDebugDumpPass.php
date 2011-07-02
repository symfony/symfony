<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class CompilerDebugDumpPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $cache = new ConfigCache($this->getCompilerLogFilename($container), false);
        $cache->write(implode("\n", $container->getCompiler()->getLog()));
    }

    static public function getCompilerLogFilename(ContainerInterface $container)
    {
        $class = $container->getParameter('kernel.container_class');

        return $container->getParameter('kernel.cache_dir').'/'.$class.'Compiler.log';
    }
}
