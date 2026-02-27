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
use Symfony\Bundle\SecurityBundle\Security\LazyFirewallContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;

class LazyFirewallContextTest extends TestCase
{
    public function testPostRequestWithOnlyLazyAuthenticatorsIsHandledLazily()
    {
        $lazyListener = $this->createMock(FirewallListenerInterface::class);
        $lazyListener->expects($this->once())->method('supports')->willReturn(null);
        // authenticate() must NOT be called eagerly
        $lazyListener->expects($this->never())->method('authenticate');

        $tokenStorage = new TokenStorage();

        $context = new LazyFirewallContext(
            [$lazyListener],
            null,
            null,
            null,
            $tokenStorage,
        );

        $request = Request::create('/public-route', 'POST');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $context->authenticate($event);

        $this->assertFalse($event->hasResponse(), 'No response should be set for a lazy firewall on a public route.');
    }
}
