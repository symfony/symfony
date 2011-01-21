<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class SecurityExtensionTest extends TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testRolesHierarchy()
    {
        $container = $this->getContainer('hierarchy');
        $this->assertEquals(array(
            'ROLE_ADMIN'       => array('ROLE_USER'),
            'ROLE_SUPER_ADMIN' => array('ROLE_USER', 'ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'),
            'ROLE_REMOTE'      => array('ROLE_USER', 'ROLE_ADMIN'),
        ), $container->getParameter('security.role_hierarchy.roles'));
    }

    public function testUserProviders()
    {
        $container = $this->getContainer('provider');

        $providers = array_values(array_filter($container->getServiceIds(), function ($key) { return 0 === strpos($key, 'security.authentication.provider.'); }));

        $this->assertEquals(array(
            'security.authentication.provider.digest',
            'security.authentication.provider.digest_0ff1b54f2a4b7f71b2b9d6604fcca4b8',
            'security.authentication.provider.basic',
            'security.authentication.provider.basic_b7f0cf21802ffc8b22cadbb255f07213',
            'security.authentication.provider.basic_98e44377704554700e68c22094b51ca4',
            'security.authentication.provider.doctrine',
            'security.authentication.provider.service',
        ), $providers);
    }

    public function testFirewalls()
    {
        $container = $this->getContainer('firewall');

        $arguments = $container->getDefinition('security.firewall.map')->getArguments();
        $listeners = array();
        foreach (array_keys($arguments[1]) as $contextId) {
            $contextDef = $container->getDefinition($contextId);
            $arguments = $contextDef->getArguments();
            $listeners[] = array_map(function ($ref) { return preg_replace('/\.[a-f0-9]+$/', '', (string) $ref); }, $arguments[0]);
        }

        $this->assertEquals(array(
            array(),
            array(
                'security.channel_listener',
                'security.logout_listener',
                'security.authentication.listener.x509',
                'security.authentication.listener.form',
                'security.authentication.listener.basic',
                'security.authentication.listener.digest',
                'security.authentication.listener.anonymous',
                'security.access_listener',
                'security.authentication.switchuser_listener',
            ),
        ), $listeners);
    }

    public function testAccess()
    {
        $container = $this->getContainer('access');

        $rules = array();
        foreach ($container->getDefinition('security.access_map')->getMethodCalls() as $call) {
            if ($call[0] == 'add') {
                $rules[] = array((string) $call[1][0], $call[1][1], $call[1][2]);
            }
        }

        $matcherIds = array();
        foreach ($rules as $rule) {
            list($matcherId, $roles, $channel) = $rule;

            $this->assertFalse(isset($matcherIds[$matcherId]));
            $matcherIds[$matcherId] = true;

            $i = count($matcherIds);
            if (1 === $i) {
                $this->assertEquals(array('ROLE_USER'), $roles);
                $this->assertEquals('https', $channel);
            } else if (2 === $i) {
                $this->assertEquals(array('IS_AUTHENTICATED_ANONYMOUSLY'), $roles);
                $this->assertNull($channel);
            }
        }
    }

    protected function getContainer($file)
    {
        $container = new ContainerBuilder();
        $security = new SecurityExtension();
        $container->registerExtension($security);
        $this->loadFromFile($container, $file);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        return $container;
    }
}
