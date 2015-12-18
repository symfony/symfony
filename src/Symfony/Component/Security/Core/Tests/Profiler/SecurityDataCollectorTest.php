<?php

namespace Symfony\Component\Security\Core\Tests\Profiler;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Profiler\SecurityDataCollector;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class SecurityDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollectWhenSecurityIsDisabled()
    {
        $collector = new SecurityDataCollector();
        $data = $collector->getCollectedData();

        $this->assertFalse($data->isEnabled());
        $this->assertFalse($data->isAuthenticated());
        $this->assertNull($data->getTokenClass());
        $this->assertFalse($data->supportsRoleHierarchy());
        $this->assertCount(0, $data->getRoles());
        $this->assertCount(0, $data->getInheritedRoles());
        $this->assertEmpty($data->getUser());
    }

    public function testCollectWhenAuthenticationTokenIsNull()
    {
        $tokenStorage = new TokenStorage();
        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy());
        $data = $collector->getCollectedData();

        $this->assertTrue($data->isEnabled());
        $this->assertFalse($data->isAuthenticated());
        $this->assertNull($data->getTokenClass());
        $this->assertTrue($data->supportsRoleHierarchy());
        $this->assertCount(0, $data->getRoles());
        $this->assertCount(0, $data->getInheritedRoles());
        $this->assertEmpty($data->getUser());
    }

    /**
     * @group legacy
     */
    public function testLegacyCollectWhenAuthenticationTokenIsNull()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy());
        $data = $collector->getCollectedData();

        $this->assertTrue($data->isEnabled());
        $this->assertFalse($data->isAuthenticated());
        $this->assertNull($data->getTokenClass());
        $this->assertTrue($data->supportsRoleHierarchy());
        $this->assertCount(0, $data->getRoles());
        $this->assertCount(0, $data->getInheritedRoles());
        $this->assertEmpty($data->getUser());
    }

    /** @dataProvider provideRoles */
    public function testCollectAuthenticationTokenAndRoles(array $roles, array $normalizedRoles, array $inheritedRoles)
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken('hhamon', 'P4$$w0rD', 'provider', $roles));

        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy());
        $data = $collector->getCollectedData();

        $this->assertTrue($data->isEnabled());
        $this->assertTrue($data->isAuthenticated());
        $this->assertSame('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $data->getTokenClass());
        $this->assertTrue($data->supportsRoleHierarchy());
        $this->assertSame($normalizedRoles, $data->getRoles());
        $this->assertSame($inheritedRoles, $data->getInheritedRoles());
        $this->assertSame('hhamon', $data->getUser());
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
}
