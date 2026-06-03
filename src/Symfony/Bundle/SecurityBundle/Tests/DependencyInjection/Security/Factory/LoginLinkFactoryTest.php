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
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\LoginLinkFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LoginLinkFactoryTest extends TestCase
{
    public function testBasicServiceConfiguration()
    {
        $container = new ContainerBuilder();

        $config = [
            'check_route' => 'app_check_login_link',
            'lifetime' => 500,
            'signature_properties' => ['email', 'password'],
            'success_handler' => 'success_handler_service_id',
            'failure_handler' => 'failure_handler_service_id',
        ];

        $factory = new LoginLinkFactory();
        $finalizedConfig = $this->processConfig($config, $factory);
        $factory->createAuthenticator($container, 'firewall1', $finalizedConfig, 'userprovider');

        $this->assertTrue($container->hasDefinition('security.authenticator.login_link'));
        $this->assertTrue($container->hasDefinition('security.authenticator.login_link_handler.firewall1'));
    }

    private function processConfig(array $config, LoginLinkFactory $factory)
    {
        $nodeDefinition = new ArrayNodeDefinition('login-link');
        $factory->addConfiguration($nodeDefinition);

        $node = $nodeDefinition->getNode();
        $normalizedConfig = $node->normalize($config);

        return $node->finalize($normalizedConfig);
    }
}
