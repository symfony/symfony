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
            ->addArgument(0, array());

        $container->addCompilerPass(new TestServiceContainerWeakRefPass(), PassConfig::TYPE_BEFORE_REMOVING, -32);
        $container->addCompilerPass(new TestServiceContainerRealRefPass(), PassConfig::TYPE_AFTER_REMOVING);

        $container->register('Test\public_service')
            ->setPublic(true)
            ->addArgument(new Reference('Test\private_used_shared_service'))
            ->addArgument(new Reference('Test\private_used_non_shared_service'))
        ;

        $container->register('Test\private_used_shared_service');
        $container->register('Test\private_unused_shared_service');
        $container->register('Test\private_used_non_shared_service')->setShared(false);
        $container->register('Test\private_unused_non_shared_service')->setShared(false);

        $container->compile();

        $expected = array(
            'Test\private_used_shared_service' => new ServiceClosureArgument(new Reference('Test\private_used_shared_service')),
            'Test\private_used_non_shared_service' => new ServiceClosureArgument(new Reference('Test\private_used_non_shared_service')),
            'Psr\Container\ContainerInterface' => new ServiceClosureArgument(new Reference('service_container')),
            'Symfony\Component\DependencyInjection\ContainerInterface' => new ServiceClosureArgument(new Reference('service_container')),
        );
        $this->assertEquals($expected, $container->getDefinition('test.private_services_locator')->getArgument(0));
        $this->assertSame($container, $container->get('test.private_services_locator')->get('Psr\Container\ContainerInterface'));
        $this->assertFalse($container->getDefinition('Test\private_used_non_shared_service')->isShared());
    }
}
