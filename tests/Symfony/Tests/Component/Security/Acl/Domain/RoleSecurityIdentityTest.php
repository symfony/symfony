<?php

namespace Symfony\Tests\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;

use Symfony\Component\Security\Role\Role;

use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;

class RoleSecurityIdentityTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $id = new RoleSecurityIdentity('ROLE_FOO');

        $this->assertEquals('ROLE_FOO', $id->getRole());
    }

    public function testConstructorWithRoleInstance()
    {
        $id = new RoleSecurityIdentity(new Role('ROLE_FOO'));

        $this->assertEquals('ROLE_FOO', $id->getRole());
    }

    /**
     * @dataProvider getCompareData
     */
    public function testEquals($id1, $id2, $equal)
    {
        if ($equal) {
            $this->assertTrue($id1->equals($id2));
        }
        else {
            $this->assertFalse($id1->equals($id2));
        }
    }

    public function getCompareData()
    {
        return array(
            array(new RoleSecurityIdentity('ROLE_FOO'), new RoleSecurityIdentity('ROLE_FOO'), true),
            array(new RoleSecurityIdentity('ROLE_FOO'), new RoleSecurityIdentity(new Role('ROLE_FOO')), true),
            array(new RoleSecurityIdentity('ROLE_USER'), new RoleSecurityIdentity('ROLE_FOO'), false),
            array(new RoleSecurityIdentity('ROLE_FOO'), new UserSecurityIdentity('ROLE_FOO', 'Foo'), false),
        );
    }
}