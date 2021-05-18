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
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @group legacy
 */
class SecurityTest extends TestCase
{
    public function testGetToken()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', 'bar'), 'provider');
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $container = $this->createContainer('security.token_storage', $tokenStorage);

        $security = new Security($container, []);
        $this->assertSame($token, $security->getToken());
    }

    /**
     * @dataProvider getUserTests
     */
    public function testGetUser($userInToken, $expectedUser)
    {
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn($userInToken);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $container = $this->createContainer('security.token_storage', $tokenStorage);

        $security = new Security($container, []);
        $this->assertSame($expectedUser, $security->getUser());
    }

    public function getUserTests()
    {
        yield [null, null];

        $user = new InMemoryUser('nice_user', 'foo');
        yield [$user, $user];
    }

    public function testIsGranted()
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('SOME_ATTRIBUTE', 'SOME_SUBJECT')
            ->willReturn(true);

        $container = $this->createContainer('security.authorization_checker', $authorizationChecker);

        $security = new Security($container, []);
        $this->assertTrue($security->isGranted('SOME_ATTRIBUTE', 'SOME_SUBJECT'));
    }

    public function testAutoLogin()
    {
        $request = new Request();
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $requestStack = $this->createMock(RequestStack::class);
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewall = new FirewallConfig('main', 'main');
        $userAuthenticator = $this->createMock(UserAuthenticatorInterface::class);
        $user = $this->createMock(UserInterface::class);
        $userChecker = $this->createMock(UserCheckerInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['request_stack', $requestStack],
                ['security.firewall.map', $firewallMap],
                ['security.user_authenticator', $userAuthenticator],
                ['security.user_checker', $userChecker],
            ])
        ;

        $requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request)
        ;

        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->willReturn($firewall)
        ;
        $userAuthenticator
            ->expects($this->once())
            ->method('authenticateUser')
            ->with($user, $authenticator, $request)
        ;
        $userChecker
            ->expects($this->once())
            ->method('checkPreAuth')
            ->with($user)
        ;

        $firewallAuthenticatorLocator = $this->createMock(ServiceProviderInterface::class);
        $firewallAuthenticatorLocator
            ->expects($this->once())
            ->method('getProvidedServices')
            ->willReturn([
                'security.authenticator.custom.dev' => $authenticator,
            ])
        ;
        $firewallAuthenticatorLocator
            ->expects($this->once())
            ->method('get')
            ->with('security.authenticator.custom.dev')
            ->willReturn($authenticator)
        ;

        $security = new Security($container, [
            'main' => $firewallAuthenticatorLocator,
        ]);

        $security->login($user);
    }

    private function createContainer($serviceId, $serviceObject)
    {
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->atLeastOnce())
            ->method('get')
            ->with($serviceId)
            ->willReturn($serviceObject);

        return $container;
    }
}
