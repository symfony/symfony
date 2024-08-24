<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\EventListener\CsrfTokenClearingLogoutListener;

class CsrfTokenClearingLogoutListenerTest extends TestCase
{
    public function testSkipsClearingSessionTokenStorageOnRequestWithoutSession()
    {
        $map = $this->createMock(FirewallMap::class);
        $map
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->willReturn(new FirewallConfig('firewall', 'user_checker'))
        ;

        try {
            (new CsrfTokenClearingLogoutListener(
                new SessionTokenStorage(new RequestStack()),
                $map
            ))->onLogout(new LogoutEvent(new Request(), null));
        } catch (SessionNotFoundException) {
            $this->fail('clear() must not be called if the request is not associated with a session instance');
        }

        $this->addToAssertionCount(1);
    }

    public function testSkipsClearingSessionTokenStorageOnStatelessRequest()
    {
        $session = new Session();

        // Create a stateless request with a previous session
        $request = new Request();
        $request->setSession($session);
        $request->cookies->set($session->getName(), 'previous_session');
        $request->attributes->set('_stateless', true);

        $map = $this->createMock(FirewallMap::class);
        $map
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->with($this->equalTo($request))
            ->willReturn(new FirewallConfig('stateless_firewall', 'user_checker', stateless: true))
        ;

        try {
            (new CsrfTokenClearingLogoutListener(
                new SessionTokenStorage(new RequestStack()),
                $map
            ))->onLogout(new LogoutEvent($request, null));
        } catch (SessionNotFoundException) {
            $this->fail('clear() must not be called if the request is stateless');
        }

        $this->addToAssertionCount(1);
    }
}
