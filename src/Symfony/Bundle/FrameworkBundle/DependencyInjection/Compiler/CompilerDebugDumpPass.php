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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\Filesystem\Filesystem;

class CompilerDebugDumpPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile(
            $this->getCompilerLogFilename($container),
            implode("\n", $container->getCompiler()->getLog()),
            0666 & ~umask()
        );
    }

    public static function getCompilerLogFilename(ContainerInterface $container)
    {
        $class = $container->getParameter('kernel.container_class');

        return $container->getParameter('kernel.cache_dir').'/'.$class.'Compiler.log';
    }
}
