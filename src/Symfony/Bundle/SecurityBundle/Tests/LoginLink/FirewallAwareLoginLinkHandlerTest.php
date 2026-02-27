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
use Symfony\Bundle\SecurityBundle\LoginLink\FirewallAwareLoginLinkHandler;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\LoginLink\LoginLinkDetails;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

class FirewallAwareLoginLinkHandlerTest extends TestCase
{
    public function testSuccessfulDecoration()
    {
        $user = new InMemoryUser('John', 'password');
        $linkDetails = new LoginLinkDetails('http://example.com', new \DateTimeImmutable());
        $request = Request::create('http://example.com/verify');

        $firewallMap = $this->createFirewallMap('main_firewall');
        $loginLinkHandler = $this->createMock(LoginLinkHandlerInterface::class);
        $loginLinkHandler->expects($this->once())
            ->method('createLoginLink')
            ->with($user, $request)
            ->willReturn($linkDetails);
        $loginLinkHandler->expects($this->once())
            ->method('consumeLoginLink')
            ->with($request)
            ->willReturn($user);
        $locator = new Container();
        $locator->set('main_firewall', $loginLinkHandler);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $linker = new FirewallAwareLoginLinkHandler($firewallMap, $locator, $requestStack);
        $actualLinkDetails = $linker->createLoginLink($user, $request);
        $this->assertSame($linkDetails, $actualLinkDetails);

        $actualUser = $linker->consumeLoginLink($request);
        $this->assertSame($user, $actualUser);
    }

    private function createFirewallMap(string $firewallName)
    {
        $context = new FirewallContext([], null, null, new FirewallConfig($firewallName, 'user_checker'));
        $locator = new Container();
        $locator->set($firewallName, $context);
        $map = new FirewallMap($locator, [$firewallName => null]);

        return $map;
    }
}
