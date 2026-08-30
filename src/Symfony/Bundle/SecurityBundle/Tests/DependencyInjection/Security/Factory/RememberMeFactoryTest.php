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

    public function testSecureDefaultIsAnAllowedValue()
    {
        $factory = new RememberMeFactory();
        $factory->addConfiguration($nodeDefinition = new ArrayNodeDefinition('remember_me'));
        $node = $nodeDefinition->getNode();

        $config = $node->finalize($node->normalize(['secret' => 'very']));

        $this->assertSame('auto', $config['secure']);
        $this->assertSame('auto', $node->finalize($node->normalize(['secret' => 'very', 'secure' => $config['secure']]))['secure']);
    }

    private function createHandlerOptions(?array $frameworkConfig, array $rememberMeConfig = []): array
    {
        $container = new ContainerBuilder();

        if (null !== $frameworkConfig) {
            $container->registerExtension(new FrameworkExtension());
            $container->loadFromExtension('framework', $frameworkConfig);
        }

        $factory = new RememberMeFactory();
        $factory->prepend($container);

        $factory->addConfiguration($nodeDefinition = new ArrayNodeDefinition('remember_me'));
        $node = $nodeDefinition->getNode();
        $config = $node->finalize($node->normalize($rememberMeConfig + ['secret' => 'very']));

        $factory->createAuthenticator($container, 'main', $config, 'security.user.provider.concrete.default');

        return $container->getDefinition('security.authenticator.remember_me_handler.main')->getArgument(3);
    }
}
