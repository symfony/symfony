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
use Symfony\Component\Security\Core\User\InMemoryUser;

class SecurityTest extends TestCase
{
    public function testGetToken()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', 'bar'), 'provider');
        $tokenStorage = self::createMock(TokenStorageInterface::class);

        $tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($token);

        $container = $this->createContainer('security.token_storage', $tokenStorage);

        $security = new Security($container);
        self::assertSame($token, $security->getToken());
    }

    /**
     * @dataProvider getUserTests
     * @dataProvider getLegacyUserTests
     */
    public function testGetUser($userInToken, $expectedUser)
    {
        $token = self::createMock(TokenInterface::class);
        $token->expects(self::any())
            ->method('getUser')
            ->willReturn($userInToken);
        $tokenStorage = self::createMock(TokenStorageInterface::class);

        $tokenStorage->expects(self::once())
            ->method('getToken')
            ->willReturn($token);

        $container = $this->createContainer('security.token_storage', $tokenStorage);

        $security = new Security($container);
        self::assertSame($expectedUser, $security->getUser());
    }

    public function getUserTests()
    {
        yield [null, null];

        $user = new InMemoryUser('nice_user', 'foo');
        yield [$user, $user];
    }

    /**
     * @group legacy
     */
    public function getLegacyUserTests()
    {
        yield ['string_username', null];

        yield [new StringishUser(), null];
    }

    public function testIsGranted()
    {
        $authorizationChecker = self::createMock(AuthorizationCheckerInterface::class);

        $authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('SOME_ATTRIBUTE', 'SOME_SUBJECT')
            ->willReturn(true);

        $container = $this->createContainer('security.authorization_checker', $authorizationChecker);

        $security = new Security($container);
        self::assertTrue($security->isGranted('SOME_ATTRIBUTE', 'SOME_SUBJECT'));
    }

    private function createContainer($serviceId, $serviceObject)
    {
        $container = self::createMock(ContainerInterface::class);

        $container->expects(self::atLeastOnce())
            ->method('get')
            ->with($serviceId)
            ->willReturn($serviceObject);

        return $container;
    }
}

class StringishUser
{
    public function __toString(): string
    {
        return 'stringish_user';
    }
}
