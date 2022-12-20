<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\LoginLink;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\LoginLink\FirewallAwareLoginLinkHandler;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkDetails;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

class FirewallAwareLoginLinkHandlerTest extends TestCase
{
    public function testSuccessfulDecoration()
    {
        $user = self::createMock(UserInterface::class);
        $linkDetails = new LoginLinkDetails('http://example.com', new \DateTimeImmutable());
        $request = Request::create('http://example.com/verify');

        $firewallMap = $this->createFirewallMap('main_firewall');
        $loginLinkHandler = self::createMock(LoginLinkHandlerInterface::class);
        $loginLinkHandler->expects(self::once())
            ->method('createLoginLink')
            ->with($user, $request)
            ->willReturn($linkDetails);
        $loginLinkHandler->expects(self::once())
            ->method('consumeLoginLink')
            ->with($request)
            ->willReturn($user);
        $locator = $this->createLocator([
            'main_firewall' => $loginLinkHandler,
        ]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $linker = new FirewallAwareLoginLinkHandler($firewallMap, $locator, $requestStack);
        $actualLinkDetails = $linker->createLoginLink($user, $request);
        self::assertSame($linkDetails, $actualLinkDetails);

        $actualUser = $linker->consumeLoginLink($request);
        self::assertSame($user, $actualUser);
    }

    private function createFirewallMap(string $firewallName)
    {
        $map = self::createMock(FirewallMap::class);
        $map->expects(self::any())
            ->method('getFirewallConfig')
            ->willReturn($config = new FirewallConfig($firewallName, 'user_checker'));

        return $map;
    }

    private function createLocator(array $linkers)
    {
        $locator = self::createMock(ContainerInterface::class);
        $locator->expects(self::any())
            ->method('has')
            ->willReturnCallback(function ($firewallName) use ($linkers) {
                return isset($linkers[$firewallName]);
            });
        $locator->expects(self::any())
            ->method('get')
            ->willReturnCallback(function ($firewallName) use ($linkers) {
                return $linkers[$firewallName];
            });

        return $locator;
    }
}
