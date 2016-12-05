<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DataCollector;

use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Http\FirewallMapInterface;

class SecurityDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollectWhenSecurityIsDisabled()
    {
        $collector = new SecurityDataCollector();
        $collector->collect($this->getRequest(), $this->getResponse());

        $this->assertSame('security', $collector->getName());
        $this->assertFalse($collector->isEnabled());
        $this->assertFalse($collector->isAuthenticated());
        $this->assertNull($collector->getTokenClass());
        $this->assertFalse($collector->supportsRoleHierarchy());
        $this->assertCount(0, $collector->getRoles());
        $this->assertCount(0, $collector->getInheritedRoles());
        $this->assertEmpty($collector->getUser());
        $this->assertNull($collector->getFirewall());
    }

    public function testCollectWhenAuthenticationTokenIsNull()
    {
        $tokenStorage = new TokenStorage();
        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy());
        $collector->collect($this->getRequest(), $this->getResponse());

        $this->assertTrue($collector->isEnabled());
        $this->assertFalse($collector->isAuthenticated());
        $this->assertNull($collector->getTokenClass());
        $this->assertTrue($collector->supportsRoleHierarchy());
        $this->assertCount(0, $collector->getRoles());
        $this->assertCount(0, $collector->getInheritedRoles());
        $this->assertEmpty($collector->getUser());
        $this->assertNull($collector->getFirewall());
    }

    /** @dataProvider provideRoles */
    public function testCollectAuthenticationTokenAndRoles(array $roles, array $normalizedRoles, array $inheritedRoles)
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken('hhamon', 'P4$$w0rD', 'provider', $roles));

        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy());
        $collector->collect($this->getRequest(), $this->getResponse());

        $this->assertTrue($collector->isEnabled());
        $this->assertTrue($collector->isAuthenticated());
        $this->assertSame('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $collector->getTokenClass());
        $this->assertTrue($collector->supportsRoleHierarchy());
        $this->assertSame($normalizedRoles, $collector->getRoles()->getRawData()[1]);
        if ($inheritedRoles) {
            $this->assertSame($inheritedRoles, $collector->getInheritedRoles()->getRawData()[1]);
        } else {
            $this->assertSame($inheritedRoles, $collector->getInheritedRoles()->getRawData()[0][0]);
        }
        $this->assertSame('hhamon', $collector->getUser());
    }

    public function testGetFirewall()
    {
        $firewallConfig = new FirewallConfig('dummy', 'security.request_matcher.dummy', 'security.user_checker.dummy');
        $request = $this->getRequest();

        $firewallMap = $this
            ->getMockBuilder(FirewallMap::class)
            ->disableOriginalConstructor()
            ->getMock();
        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn($firewallConfig);

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap);
        $collector->collect($request, $this->getResponse());
        $collected = $collector->getFirewall();

        $this->assertSame($firewallConfig->getName(), $collected['name']);
        $this->assertSame($firewallConfig->allowsAnonymous(), $collected['allows_anonymous']);
        $this->assertSame($firewallConfig->getRequestMatcher(), $collected['request_matcher']);
        $this->assertSame($firewallConfig->isSecurityEnabled(), $collected['security_enabled']);
        $this->assertSame($firewallConfig->isStateless(), $collected['stateless']);
        $this->assertSame($firewallConfig->getProvider(), $collected['provider']);
        $this->assertSame($firewallConfig->getContext(), $collected['context']);
        $this->assertSame($firewallConfig->getEntryPoint(), $collected['entry_point']);
        $this->assertSame($firewallConfig->getAccessDeniedHandler(), $collected['access_denied_handler']);
        $this->assertSame($firewallConfig->getAccessDeniedUrl(), $collected['access_denied_url']);
        $this->assertSame($firewallConfig->getUserChecker(), $collected['user_checker']);
        $this->assertSame($firewallConfig->getListeners(), $collected['listeners']->getRawData()[0][0]);
    }

    public function testGetFirewallReturnsNull()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();

        // Don't inject any firewall map
        $collector = new SecurityDataCollector();
        $collector->collect($request, $response);
        $this->assertNull($collector->getFirewall());

        // Inject an instance that is not context aware
        $firewallMap = $this
            ->getMockBuilder(FirewallMapInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap);
        $collector->collect($request, $response);
        $this->assertNull($collector->getFirewall());

        // Null config
        $firewallMap = $this
            ->getMockBuilder(FirewallMap::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap);
        $collector->collect($request, $response);
        $this->assertNull($collector->getFirewall());
    }

    public function provideRoles()
    {
        return array(
            // Basic roles
            array(
                array('ROLE_USER'),
                array('ROLE_USER'),
                array(),
            ),
            array(
                array(new Role('ROLE_USER')),
                array('ROLE_USER'),
                array(),
            ),
            // Inherited roles
            array(
                array('ROLE_ADMIN'),
                array('ROLE_ADMIN'),
                array('ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'),
            ),
            array(
                array(new Role('ROLE_ADMIN')),
                array('ROLE_ADMIN'),
                array('ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'),
            ),
        );
    }

    private function getRoleHierarchy()
    {
        return new RoleHierarchy(array(
            'ROLE_ADMIN' => array('ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'),
        ));
    }

    private function getRequest()
    {
        return $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getResponse()
    {
        return $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\Response')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
