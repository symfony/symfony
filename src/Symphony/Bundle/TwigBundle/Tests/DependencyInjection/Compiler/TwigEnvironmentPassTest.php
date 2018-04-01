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
use Symphony\Bridge\Twig\Extension\FormExtension;
use Symphony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigEnvironmentPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Reference;

class TwigEnvironmentPassTest extends TestCase
{
    public function testPassWithTwoExtensionsWithPriority()
    {
        $twigDefinition = new Definition('twig');
        $twigDefinition->setPublic(true);
        $builder = new ContainerBuilder();
        $builder->setDefinition('twig', $twigDefinition);
        $pass = new TwigEnvironmentPass();

        $definition = new Definition('test_extension_1');
        $definition->addTag('twig.extension', array('priority' => 100));
        $builder->setDefinition('test_extension_1', $definition);

        $definition = new Definition('test_extension_2');
        $definition->addTag('twig.extension', array('priority' => 200));
        $builder->setDefinition('test_extension_2', $definition);

        $pass->process($builder);
        $calls = $twigDefinition->getMethodCalls();
        $this->assertCount(2, $calls);
        $this->assertEquals('addExtension', $calls[0][0]);
        $this->assertEquals('addExtension', $calls[1][0]);
        $this->assertEquals('test_extension_2', (string) $calls[0][1][0]);
        $this->assertEquals('test_extension_1', (string) $calls[1][1][0]);
    }

    public function testTwigBridgeExtensionsAreRegisteredFirst()
    {
        $container = new ContainerBuilder();
        $twigDefinition = $container->register('twig');
        $container->register('other_extension', 'Foo\Bar')
            ->addTag('twig.extension');
        $container->register('twig_bridge_extension', FormExtension::class)
            ->addTag('twig.extension');

        $twigEnvironmentPass = new TwigEnvironmentPass();
        $twigEnvironmentPass->process($container);

        $methodCalls = $twigDefinition->getMethodCalls();
        $this->assertCount(2, $methodCalls);

        $twigBridgeExtensionReference = $methodCalls[0][1][0];
        $this->assertInstanceOf(Reference::class, $twigBridgeExtensionReference);
        $this->assertSame('twig_bridge_extension', (string) $twigBridgeExtensionReference);

        $otherExtensionReference = $methodCalls[1][1][0];
        $this->assertInstanceOf(Reference::class, $otherExtensionReference);
        $this->assertSame('other_extension', (string) $otherExtensionReference);
    }
}
