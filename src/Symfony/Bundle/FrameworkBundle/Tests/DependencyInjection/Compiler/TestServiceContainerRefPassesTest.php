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
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TestServiceContainerRealRefPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TestServiceContainerWeakRefPass;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

class TestServiceContainerRefPassesTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('test.private_services_locator', ServiceLocator::class)
            ->setPublic(true)
            ->addArgument(0, []);

        $container->addCompilerPass(new TestServiceContainerWeakRefPass(), PassConfig::TYPE_BEFORE_REMOVING, -32);
        $container->addCompilerPass(new TestServiceContainerRealRefPass(), PassConfig::TYPE_AFTER_REMOVING);

        $container->register('Test\public_service')
            ->setPublic(true)
            ->addArgument(new Reference('Test\private_used_shared_service'))
            ->addArgument(new Reference('Test\private_used_non_shared_service'))
            ->addArgument(new Reference('Test\soon_private_service'))
        ;

        $container->register('Test\soon_private_service')
            ->setPublic(true)
            ->addTag('container.private', ['package' => 'foo/bar', 'version' => '1.42'])
        ;
        $container->register('Test\soon_private_service_decorated')
            ->setPublic(true)
            ->addTag('container.private', ['package' => 'foo/bar', 'version' => '1.42'])
        ;
        $container->register('Test\soon_private_service_decorator')
            ->setDecoratedService('Test\soon_private_service_decorated')
            ->setArguments(['Test\soon_private_service_decorator.inner']);

        $container->register('Test\private_used_shared_service');
        $container->register('Test\private_unused_shared_service');
        $container->register('Test\private_used_non_shared_service')->setShared(false);
        $container->register('Test\private_unused_non_shared_service')->setShared(false);

        $container->compile();

        $expected = [
            'Test\private_used_shared_service' => new ServiceClosureArgument(new Reference('Test\private_used_shared_service')),
            'Test\private_used_non_shared_service' => new ServiceClosureArgument(new Reference('Test\private_used_non_shared_service')),
            'Test\soon_private_service' => new ServiceClosureArgument(new Reference('.container.private.Test\soon_private_service')),
            'Test\soon_private_service_decorator' => new ServiceClosureArgument(new Reference('.container.private.Test\soon_private_service_decorated')),
            'Test\soon_private_service_decorated' => new ServiceClosureArgument(new Reference('.container.private.Test\soon_private_service_decorated')),
        ];

        $privateServices = $container->getDefinition('test.private_services_locator')->getArgument(0);
        unset($privateServices[\Symfony\Component\DependencyInjection\ContainerInterface::class], $privateServices[ContainerInterface::class]);

        $this->assertEquals($expected, $privateServices);
        $this->assertFalse($container->getDefinition('Test\private_used_non_shared_service')->isShared());
    }
}
