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
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AbstractFactoryTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
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
            $this->container->register($serviceId, \stdClass::class);
        }

        $this->callFactory('foo', $options, 'user_provider', 'entry_point');

        $failureHandler = $this->container->getDefinition('security.authentication.failure_handler.foo.stub');

        $methodCalls = $failureHandler->getMethodCalls();
        if ($defaultHandlerInjection) {
            $this->assertEquals('setOptions', $methodCalls[0][0]);
            $this->assertEquals(['login_path' => '/bar'], $methodCalls[0][1][0]);
        } else {
            $this->assertCount(0, $methodCalls);
        }
    }

    public function getFailureHandlers()
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
            $this->container->register($serviceId, \stdClass::class);
        }

        $this->callFactory('foo', $options, 'user_provider', 'entry_point');

        $successHandler = $this->container->getDefinition('security.authentication.success_handler.foo.stub');
        $methodCalls = $successHandler->getMethodCalls();

        if ($defaultHandlerInjection) {
            $this->assertEquals('setOptions', $methodCalls[0][0]);
            $this->assertEquals(['default_target_path' => '/bar'], $methodCalls[0][1][0]);
            $this->assertEquals('setFirewallName', $methodCalls[1][0]);
            $this->assertEquals(['foo'], $methodCalls[1][1]);
        } else {
            $this->assertCount(0, $methodCalls);
        }
    }

    public function getSuccessHandlers()
    {
        return [
            [null, true],
            ['custom_success_handler', false],
        ];
    }

    protected function callFactory(string $firewallName, array $config, string $userProviderId, string $defaultEntryPointId)
    {
        (new StubFactory())->createAuthenticator($this->container, $firewallName, $config, $userProviderId);
    }
}

class StubFactory extends AbstractFactory
{
    public function getPriority(): int
    {
        return 0;
    }

    public function getKey(): string
    {
        return 'stub';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $this->createAuthenticationSuccessHandler($container, $firewallName, $config);
        $this->createAuthenticationFailureHandler($container, $firewallName, $config);

        return 'stub_authenticator_id';
    }
}
