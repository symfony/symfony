<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\User;

class SecurityTest extends TestCase
{
    public function testGetToken()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'provider');
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $container = $this->createContainer('security.token_storage', $tokenStorage);

        $security = new Security($container);
        $this->assertSame($token, $security->getToken());
    }

    /**
     * @dataProvider getUserTests
     */
    public function testGetUser($userInToken, $expectedUser)
    {
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($userInToken));
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $container = $this->createContainer('security.token_storage', $tokenStorage);

        $security = new Security($container);
        $this->assertSame($expectedUser, $security->getUser());
    }

    public function getUserTests()
    {
        yield array(null, null);

        yield array('string_username', null);

        $user = new User('nice_user', 'foo');
        yield array($user, $user);
    }

    public function testIsGranted()
    {
        $authorizationChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();

        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('SOME_ATTRIBUTE', 'SOME_SUBJECT')
            ->will($this->returnValue(true));

        $container = $this->createContainer('security.authorization_checker', $authorizationChecker);

        $security = new Security($container);
        $this->assertTrue($security->isGranted('SOME_ATTRIBUTE', 'SOME_SUBJECT'));
    }

    private function createContainer($serviceId, $serviceObject)
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();

        $container->expects($this->atLeastOnce())
            ->method('get')
            ->with($serviceId)
            ->will($this->returnValue($serviceObject));

        return $container;
    }
}
