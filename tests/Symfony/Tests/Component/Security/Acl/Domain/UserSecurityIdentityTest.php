<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;

class UserSecurityIdentityTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $id = new UserSecurityIdentity('foo', 'Foo');

        $this->assertEquals('foo', $id->getUsername());
        $this->assertEquals('Foo', $id->getClass());
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
        $account = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $account
            ->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue('foo'))
        ;

        return array(
            array(new UserSecurityIdentity('foo', 'Foo'), new UserSecurityIdentity('foo', 'Foo'), true),
            array(new UserSecurityIdentity('foo', 'Bar'), new UserSecurityIdentity('foo', 'Foo'), false),
            array(new UserSecurityIdentity('foo', 'Foo'), new UserSecurityIdentity('bar', 'Foo'), false),
            array(new UserSecurityIdentity('foo', 'Foo'), UserSecurityIdentity::fromAccount($account), false),
            array(new UserSecurityIdentity('bla', 'Foo'), new UserSecurityIdentity('blub', 'Foo'), false),
            array(new UserSecurityIdentity('foo', 'Foo'), new RoleSecurityIdentity('foo'), false),
        );
    }
}
