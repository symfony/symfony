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

use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Doctrine\Common\Util\ClassUtils;

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
        $this->assertSame($equal, $id1->equals($id2));
    }

    public function getCompareData()
    {
        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')
                            ->setMockClassName('USI_AccountImpl')
                            ->getMock();
        $account
            ->expects($this->any())
            ->method('getUsername')
            ->will($this->returnValue('foo'))
        ;

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token
            ->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($account))
        ;

        $data = array(
            array(new UserSecurityIdentity('foo', 'Foo'), new UserSecurityIdentity('foo', 'Foo'), true),
            array(new UserSecurityIdentity('foo', 'Bar'), new UserSecurityIdentity('foo', 'Foo'), false),
            array(new UserSecurityIdentity('foo', 'Foo'), new UserSecurityIdentity('bar', 'Foo'), false),
            array(new UserSecurityIdentity('foo', 'Foo'), UserSecurityIdentity::fromAccount($account), false),
            array(new UserSecurityIdentity('bla', 'Foo'), new UserSecurityIdentity('blub', 'Foo'), false),
            array(new UserSecurityIdentity('foo', 'Foo'), new RoleSecurityIdentity('foo'), false),
            array(new UserSecurityIdentity('foo', 'Foo'), UserSecurityIdentity::fromToken($token), false),
            array(new UserSecurityIdentity('foo', 'USI_AccountImpl'), UserSecurityIdentity::fromToken($token), true),
        );

        if (class_exists('Doctrine\Common\Util\ClassUtils')) {
            $proxyClass = ClassUtils::generateProxyClassName('Foo', 'Acme\\DemoBundle\\Proxy\\');
            $data[] = array(new UserSecurityIdentity('foo', $proxyClass), new UserSecurityIdentity('foo', 'Foo'), true);
        }

        return $data;
    }
}
