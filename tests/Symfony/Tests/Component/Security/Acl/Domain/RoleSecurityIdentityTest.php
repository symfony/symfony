<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;

class RoleSecurityIdentityTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getConstructorData
     */
    public function testConstructor($role, $roleString)
    {
        $id = new RoleSecurityIdentity($role);

        $this->assertEquals($roleString, $id->getRole());
    }

     public function getConstructorData()
    {
        return array(
            array('ROLE_FOO', 'ROLE_FOO'),
            array(new Role('ROLE_FOO'), 'ROLE_FOO'),
            array(new CustomRole(), 'CUSTOM_ROLE'),
        );
    }

    /**
     * @dataProvider getCompareData
     */
    public function testEquals($id1, $id2, $equal)
    {
        if ($equal) {
            $this->assertTrue($id1->equals($id2));
        } else {
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

class CustomRole implements \Symfony\Component\Security\Core\Role\RoleInterface
{
    /**
     * {@inheritDoc}
     */
    public function getRole()
    {
        return 'CUSTOM_ROLE';
    }
}
