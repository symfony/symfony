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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\RememberMeFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Cookie;

class RememberMeFactoryTest extends TestCase
{
    public function testCookieFollowsTheSessionCookieDefaults()
    {
        $options = $this->createHandlerOptions(['session' => ['enabled' => true]]);

        $this->assertNull($options['secure']);
        $this->assertSame(Cookie::SAMESITE_LAX, $options['samesite']);
    }

    public function testCookieFollowsTheConfiguredSessionCookie()
    {
        $options = $this->createHandlerOptions(['session' => ['enabled' => true, 'cookie_secure' => false, 'cookie_samesite' => null]]);

        $this->assertFalse($options['secure']);
        $this->assertNull($options['samesite']);
    }

    public function testCookieDefaultsWithoutFrameworkBundle()
    {
        $options = $this->createHandlerOptions(null);

        $this->assertNull($options['secure']);
        $this->assertSame(Cookie::SAMESITE_LAX, $options['samesite']);
    }

    public function testFirewallConfigWinsOverTheSessionCookie()
    {
        $options = $this->createHandlerOptions(['session' => ['enabled' => true]], ['secure' => false, 'samesite' => null]);

        $this->assertFalse($options['secure']);
        $this->assertNull($options['samesite']);
    }

    public function testSecureIsAnAllowedValue()
    {
        $config = $this->createFirewallConfig(null, ['secure' => 'auto']);

        $this->assertSame('auto', $config['secure']);
    }

    public function testTheConfigTreeDoesNotDependOnTheSessionCookie()
    {
        $withSessionDefaults = $this->createFirewallConfig(['session' => ['enabled' => true]]);
        $withConfiguredSession = $this->createFirewallConfig(['session' => ['enabled' => true, 'cookie_secure' => false, 'cookie_samesite' => null]]);

        $this->assertSame($withSessionDefaults, $withConfiguredSession);
        $this->assertArrayNotHasKey('secure', $withSessionDefaults);
        $this->assertArrayNotHasKey('samesite', $withSessionDefaults);
    }

    private function createHandlerOptions(?array $frameworkConfig, array $rememberMeConfig = []): array
    {
        $container = new ContainerBuilder();
        $factory = $this->createFactory($container, $frameworkConfig);

        $factory->createAuthenticator($container, 'main', $this->processConfig($factory, $rememberMeConfig), 'security.user.provider.concrete.default');

        return $container->getDefinition('security.authenticator.remember_me_handler.main')->getArgument(3);
    }

    private function createFirewallConfig(?array $frameworkConfig, array $rememberMeConfig = []): array
    {
        return $this->processConfig($this->createFactory(new ContainerBuilder(), $frameworkConfig), $rememberMeConfig);
    }

    private function createFactory(ContainerBuilder $container, ?array $frameworkConfig): RememberMeFactory
    {
        if (null !== $frameworkConfig) {
            $container->registerExtension(new FrameworkExtension());
            $container->loadFromExtension('framework', $frameworkConfig);
        }

        $factory = new RememberMeFactory();
        $factory->prepend($container);

        return $factory;
    }

    private function processConfig(RememberMeFactory $factory, array $rememberMeConfig): array
    {
        $factory->addConfiguration($nodeDefinition = new ArrayNodeDefinition('remember_me'));
        $node = $nodeDefinition->getNode();

        return $node->finalize($node->normalize($rememberMeConfig + ['secret' => 'very']));
    }
}
