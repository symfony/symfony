<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\SecurityExtension;

class SecurityExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testIsGranted()
    {
        $checker = $this->getMock('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface');
        $checker->expects($this->once())->method('isGranted')->will($this->returnValue(true));
        $storage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');

        $extension = new SecurityExtension($checker, $storage);
        $this->assertTrue($extension->isGranted('ROLE_ADMIN'));
    }

    public function testGetUserWithoutToken()
    {
        $checker = $this->getMock('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface');
        $storage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $storage->expects($this->once())->method('getToken')->will($this->returnValue(null));

        $extension = new SecurityExtension($checker, $storage);
        $this->assertNull($extension->getUser());
    }

    public function testGetUserWithNullUser()
    {
        $checker = $this->getMock('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface');

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->once())->method('getUser')->will($this->returnValue(null));

        $storage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $storage->expects($this->once())->method('getToken')->will($this->returnValue($token));

        $extension = new SecurityExtension($checker, $storage);
        $this->assertNull($extension->getUser());
    }

    public function testGetUserWithUser()
    {
        $checker = $this->getMock('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface');

        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->once())->method('getUser')->will($this->returnValue($user));

        $storage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $storage->expects($this->once())->method('getToken')->will($this->returnValue($token));

        $extension = new SecurityExtension($checker, $storage);
        $this->assertSame($user, $extension->getUser());
    }
}
