<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\TwigBundle\DependencyInjection\Compiler\ExtensionPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;

class ExtensionPassTest extends TestCase
{
    public function testProcessDoesNotDropExistingFileLoaderMethodCalls()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $container->register('twig.app_variable', '\Symphony\Bridge\Twig\AppVariable');
        $container->register('templating', '\Symphony\Bundle\TwigBundle\TwigEngine');
        $container->register('twig.extension.yaml');
        $container->register('twig.extension.debug.stopwatch');
        $container->register('twig.extension.expression');

        $nativeTwigLoader = new Definition('\Twig\Loader\FilesystemLoader');
        $nativeTwigLoader->addMethodCall('addPath', array());
        $container->setDefinition('twig.loader.native_filesystem', $nativeTwigLoader);

        $filesystemLoader = new Definition('\Symphony\Bundle\TwigBundle\Loader\FilesystemLoader');
        $filesystemLoader->setArguments(array(null, null, null));
        $filesystemLoader->addMethodCall('addPath', array());
        $container->setDefinition('twig.loader.filesystem', $filesystemLoader);

        $extensionPass = new ExtensionPass();
        $extensionPass->process($container);

        $this->assertCount(2, $filesystemLoader->getMethodCalls());
    }
}
