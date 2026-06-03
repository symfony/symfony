<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\RegisterReverseContainerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ReverseContainer;

class RegisterReverseContainerPassTest extends TestCase
{
    public function testCompileRemovesUnusedServices()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass');
        $container->register('reverse_container', ReverseContainer::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument(new ServiceLocatorArgument([]))
            ->setPublic(true);

        $container->addCompilerPass(new RegisterReverseContainerPass(true));
        $container->compile();

        $this->assertFalse($container->has('foo'));
    }

    public function testPublicServices()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->setPublic(true);
        $container->register('reverse_container', ReverseContainer::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument(new ServiceLocatorArgument([]))
            ->setPublic(true);

        $container->addCompilerPass(new RegisterReverseContainerPass(true));
        $container->addCompilerPass(new RegisterReverseContainerPass(false), PassConfig::TYPE_AFTER_REMOVING);
        $container->compile();

        $foo = $container->get('foo');

        $this->assertSame('foo', $container->get('reverse_container')->getId($foo));
        $this->assertSame($foo, $container->get('reverse_container')->getService('foo'));
    }

    public function testReversibleServices()
    {
        $container = new ContainerBuilder();
        $container->register('bar', 'stdClass')->setProperty('foo', new Reference('foo'))->setPublic(true);
        $container->register('foo', 'stdClass')->addTag('container.reversible');
        $container->register('reverse_container', ReverseContainer::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument(new ServiceLocatorArgument([]))
            ->setPublic(true);

        $container->addCompilerPass(new RegisterReverseContainerPass(true));
        $container->addCompilerPass(new RegisterReverseContainerPass(false), PassConfig::TYPE_AFTER_REMOVING);
        $container->compile();

        $foo = $container->get('bar')->foo;

        $this->assertSame('foo', $container->get('reverse_container')->getId($foo));
        $this->assertSame($foo, $container->get('reverse_container')->getService('foo'));
    }
}
