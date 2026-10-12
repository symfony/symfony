<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TestStatePass;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

class TestStatePassTest extends TestCase
{
    public function testProcess()
    {
        $container = $this->createContainer();
        $container->register('kept')->addTag('kernel.test_state', ['keep' => true]);
        $container->register('cleared')
            ->addTag('kernel.test_state', ['method' => 'clear'])
            ->addTag('kernel.test_state', ['method' => 'prune']);
        $container->register('kept_and_cleared')->addTag('kernel.test_state', ['keep' => true, 'method' => 'clear']);
        $container->register('untagged');

        (new TestStatePass())->process($container);

        $definition = $container->getDefinition('test.service_container');
        $this->assertEquals(new IteratorArgument([
            'kept' => new Reference('kept', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
            'kept_and_cleared' => new Reference('kept_and_cleared', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
        ]), $definition->getArgument(4));
        $this->assertSame(['cleared' => ['clear', 'prune'], 'kept_and_cleared' => ['clear']], $definition->getArgument(5));
    }

    public function testTagRequiresKeepOrMethod()
    {
        $container = $this->createContainer();
        $container->register('foo')->addTag('kernel.test_state', ['keep' => false]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag "kernel.test_state" requires the "keep" or the "method" attribute to be set on service "foo".');

        (new TestStatePass())->process($container);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('test.service_container', TestContainer::class)
            ->setArguments([new Reference('kernel'), 'test.private_services_locator', [], [], [], []]);

        return $container;
    }
}
