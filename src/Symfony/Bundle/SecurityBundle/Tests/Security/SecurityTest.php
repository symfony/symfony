<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Bundle\SecurityBundle\Security\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

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

        $security = new Security($container);
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

        $security = new Security($container);
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

        $security = new Security($container);
        $this->assertTrue($security->isGranted('SOME_ATTRIBUTE', 'SOME_SUBJECT'));
    }

    /**
     * @dataProvider getFirewallConfigTests
     */
    public function testGetFirewallConfig(Request $request, ?FirewallConfig $expectedFirewallConfig)
    {
        $firewallMap = $this->createMock(FirewallMap::class);

        $firewallMap->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn($expectedFirewallConfig);

        $container = $this->createContainer('security.firewall.map', $firewallMap);

        $security = new Security($container);
        $this->assertSame($expectedFirewallConfig, $security->getFirewallConfig($request));
    }

    public function getFirewallConfigTests()
    {
        $request = new Request();

        yield [$request, null];
        yield [$request, new FirewallConfig('main', 'acme_user_checker')];
    }

    private function createContainer(string $serviceId, object $serviceObject): ContainerInterface
    {
        return new ServiceLocator([$serviceId => fn () => $serviceObject]);
    }
}
