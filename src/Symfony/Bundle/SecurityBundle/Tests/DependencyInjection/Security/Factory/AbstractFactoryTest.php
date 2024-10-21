<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Security\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AbstractFactoryTest extends TestCase
{
    public function testCreate()
    {
        [$container, $authProviderId, $listenerId, $entryPointId] = $this->callFactory('foo', [
            'use_forward' => true,
            'failure_path' => '/foo',
            'success_handler' => 'custom_success_handler',
            'failure_handler' => 'custom_failure_handler',
            'remember_me' => true,
        ], 'user_provider', 'entry_point');

        // auth provider
        $this->assertEquals('auth_provider', $authProviderId);

        // listener
        $this->assertEquals('abstract_listener.foo', $listenerId);
        $this->assertTrue($container->hasDefinition('abstract_listener.foo'));
        $definition = $container->getDefinition('abstract_listener.foo');
        $this->assertEquals([
            'index_4' => 'foo',
            'index_5' => new Reference('security.authentication.success_handler.foo.abstract_factory'),
            'index_6' => new Reference('security.authentication.failure_handler.foo.abstract_factory'),
            'index_7' => [
                'use_forward' => true,
            ],
        ], $definition->getArguments());

        // entry point
        $this->assertEquals('entry_point', $entryPointId, '->create() does not change the default entry point.');
    }

    /**
     * @dataProvider getFailureHandlers
     */
    public function testDefaultFailureHandler($serviceId, $defaultHandlerInjection)
    {
        $options = [
            'remember_me' => true,
            'login_path' => '/bar',
        ];

        if ($serviceId) {
            $options['failure_handler'] = $serviceId;
        }

        [$container] = $this->callFactory('foo', $options, 'user_provider', 'entry_point');

        $definition = $container->getDefinition('abstract_listener.foo');
        $arguments = $definition->getArguments();
        $this->assertEquals(new Reference('security.authentication.failure_handler.foo.abstract_factory'), $arguments['index_6']);
        $failureHandler = $container->findDefinition((string) $arguments['index_6']);

        $expectedFailureHandlerOptions = ['login_path' => '/bar'];
        $methodCalls = $failureHandler->getMethodCalls();
        if ($defaultHandlerInjection) {
            $this->assertEquals('setOptions', $methodCalls[0][0]);
            $this->assertEquals($expectedFailureHandlerOptions, $methodCalls[0][1][0]);
        } else {
            $this->assertCount(0, $methodCalls);
            $this->assertInstanceOf(ChildDefinition::class, $failureHandler);
            $this->assertEquals('security.authentication.custom_failure_handler', $failureHandler->getParent());
            $failureHandlerArguments = $failureHandler->getArguments();
            $this->assertInstanceOf(ChildDefinition::class, $failureHandlerArguments['index_0']);
            $this->assertEquals($serviceId, $failureHandlerArguments['index_0']->getParent());
            $this->assertEquals($expectedFailureHandlerOptions, $failureHandlerArguments['index_1']);
        }
    }

    public static function getFailureHandlers()
    {
        return [
            [null, true],
            ['custom_failure_handler', false],
        ];
    }

    /**
     * @dataProvider getSuccessHandlers
     */
    public function testDefaultSuccessHandler($serviceId, $defaultHandlerInjection)
    {
        $options = [
            'remember_me' => true,
            'default_target_path' => '/bar',
        ];

        if ($serviceId) {
            $options['success_handler'] = $serviceId;
        }

        [$container] = $this->callFactory('foo', $options, 'user_provider', 'entry_point');

        $definition = $container->getDefinition('abstract_listener.foo');
        $arguments = $definition->getArguments();
        $this->assertEquals(new Reference('security.authentication.success_handler.foo.abstract_factory'), $arguments['index_5']);
        $successHandler = $container->findDefinition((string) $arguments['index_5']);
        $methodCalls = $successHandler->getMethodCalls();

        $expectedSuccessHandlerOptions = ['default_target_path' => '/bar'];
        $expectedFirewallName = 'foo';
        if ($defaultHandlerInjection) {
            $this->assertEquals('setOptions', $methodCalls[0][0]);
            $this->assertEquals($expectedSuccessHandlerOptions, $methodCalls[0][1][0]);
            $this->assertEquals('setFirewallName', $methodCalls[1][0]);
            $this->assertEquals($expectedFirewallName, $methodCalls[1][1][0]);
        } else {
            $this->assertCount(0, $methodCalls);
            $this->assertInstanceOf(ChildDefinition::class, $successHandler);
            $this->assertEquals('security.authentication.custom_success_handler', $successHandler->getParent());
            $successHandlerArguments = $successHandler->getArguments();
            $this->assertInstanceOf(ChildDefinition::class, $successHandlerArguments['index_0']);
            $this->assertEquals($serviceId, $successHandlerArguments['index_0']->getParent());
            $this->assertEquals($expectedSuccessHandlerOptions, $successHandlerArguments['index_1']);
            $this->assertEquals($expectedFirewallName, $successHandlerArguments['index_2']);
        }
    }

    public static function getSuccessHandlers()
    {
        return [
            [null, true],
            ['custom_success_handler', false],
        ];
    }

    protected function callFactory($id, $config, $userProviderId, $defaultEntryPointId)
    {
        $factory = $this->getMockBuilder(AbstractFactory::class)->onlyMethods([
            'createAuthProvider',
            'getListenerId',
            'getKey',
            'getPosition',
        ])->getMock();

        $factory
            ->expects($this->once())
            ->method('createAuthProvider')
            ->willReturn('auth_provider')
        ;
        $factory
            ->expects($this->atLeastOnce())
            ->method('getListenerId')
            ->willReturn('abstract_listener')
        ;
        $factory
            ->expects($this->any())
            ->method('getKey')
            ->willReturn('abstract_factory')
        ;

        $container = new ContainerBuilder();
        $container->register('auth_provider');
        $container->register('custom_success_handler');
        $container->register('custom_failure_handler');

        [$authProviderId, $listenerId, $entryPointId] = $factory->create($container, $id, $config, $userProviderId, $defaultEntryPointId);

        return [$container, $authProviderId, $listenerId, $entryPointId];
    }
}
