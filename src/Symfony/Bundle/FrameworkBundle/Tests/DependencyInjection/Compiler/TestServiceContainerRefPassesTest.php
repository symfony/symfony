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

        $container->register('test.public_service', 'stdClass')
            ->setPublic(true)
            ->addArgument(new Reference('test.private_used_shared_service'))
            ->addArgument(new Reference('test.private_used_non_shared_service'))
            ->addArgument(new Reference('test.soon_private_service'))
        ;

        $container->register('test.soon_private_service', 'stdClass')
            ->setPublic(true)
            ->addTag('container.private', ['package' => 'foo/bar', 'version' => '1.42'])
        ;
        $container->register('test.soon_private_service_decorated', 'stdClass')
            ->setPublic(true)
            ->addTag('container.private', ['package' => 'foo/bar', 'version' => '1.42'])
        ;
        $container->register('test.soon_private_service_decorator', 'stdClass')
            ->setDecoratedService('test.soon_private_service_decorated')
            ->setArguments(['test.soon_private_service_decorator.inner']);

        $container->register('test.private_used_shared_service', 'stdClass');
        $container->register('test.private_unused_shared_service', 'stdClass');
        $container->register('test.private_used_non_shared_service', 'stdClass')->setShared(false);
        $container->register('test.private_unused_non_shared_service', 'stdClass')->setShared(false);

        $container->compile();

        $expected = [
            'test.private_used_shared_service' => new ServiceClosureArgument(new Reference('test.private_used_shared_service')),
            'test.private_used_non_shared_service' => new ServiceClosureArgument(new Reference('test.private_used_non_shared_service')),
            'test.soon_private_service' => new ServiceClosureArgument(new Reference('.container.private.test.soon_private_service')),
            'test.soon_private_service_decorator' => new ServiceClosureArgument(new Reference('.container.private.test.soon_private_service_decorated')),
            'test.soon_private_service_decorated' => new ServiceClosureArgument(new Reference('.container.private.test.soon_private_service_decorated')),
        ];

        $privateServices = $container->getDefinition('test.private_services_locator')->getArgument(0);
        unset($privateServices[\Symfony\Component\DependencyInjection\ContainerInterface::class], $privateServices[ContainerInterface::class]);

        $this->assertEquals($expected, $privateServices);
        $this->assertFalse($container->getDefinition('test.private_used_non_shared_service')->isShared());
    }
}
