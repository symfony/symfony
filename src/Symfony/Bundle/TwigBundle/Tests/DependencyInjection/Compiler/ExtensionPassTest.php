<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\ExtensionPass;
use Symfony\Bundle\TwigBundle\TemplateIterator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

class ExtensionPassTest extends TestCase
{
    public function testProcessDoesNotDropExistingFileLoaderMethodCalls()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $container->register('twig.app_variable', AppVariable::class);
        $container->register('twig.extension.yaml');
        $container->register('twig.extension.debug.stopwatch');
        $container->register('twig.extension.expression');

        $nativeTwigLoader = new Definition(FilesystemLoader::class);
        $nativeTwigLoader->addMethodCall('addPath', []);
        $container->setDefinition('twig.loader.native_filesystem', $nativeTwigLoader);

        $templateIterator = new Definition(TemplateIterator::class, [null, null, null]);
        $container->setDefinition('twig.template_iterator', $templateIterator);

        $extensionPass = new ExtensionPass();
        $extensionPass->process($container);

        $this->assertCount(1, $nativeTwigLoader->getMethodCalls());
    }
}
