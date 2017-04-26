<?php

namespace Symfony\Component\Security\Core\Tests\Authorization;

use Symfony\Component\Security\Core\Authorization\RoleChecker;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class RoleCheckerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getHasRoleTests
     */
    public function testHasRole($role, $user, $expected)
    {
        $extension = new RoleChecker(
            $this->getAuthorizationChecker(),
            new RoleHierarchy(array('ROLE_FOO' => array('ROLE_FOOBAR')))
        );

        $this->assertSame($expected, $extension->hasRole($role, $user));
    }

    public function getHasRoleTests()
    {
        return array(
            array('', $this->getUser(array()), false),
            array('ROLE_FOO', $this->getUser(array()), false),
            array('ROLE_FOO', $this->getUser(array('ROLE_FOOBAR')), false),
            array('ROLE_FOO', $this->getUser(array('ROLE_FOO')), true),
            array('ROLE_FOOBAR', $this->getUser(array('ROLE_FOO')), true),

            array('ROLE_FOO', null, true),
            array('ROLE_BAR', null, false),
        );
    }

    protected function getAuthorizationChecker()
    {
        $authorizationChecker = $this->getMock('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface');
        $authorizationChecker
            ->method('isGranted')
            ->will($this->returnValueMap(array(
                array('ROLE_FOO', null, true),
                array('ROLE_BAR', null, false),
            )));

        return $authorizationChecker;
    }

    protected function getUser(array $roles)
    {
        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');
        $user->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue($roles));

        return $user;
    }
}
