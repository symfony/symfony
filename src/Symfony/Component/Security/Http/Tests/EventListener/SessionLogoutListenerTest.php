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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\EventListener\SessionLogoutListener;

class SessionLogoutListenerTest extends TestCase
{
    public function testOnLogoutIfHasNoSession()
    {
        $request = $this->createMock(Request::class);
        $request->method('hasSession')->willReturn(false);
        $request->expects($this->never())->method('getSession');

        $sessionLogoutListener = new SessionLogoutListener();
        $sessionLogoutListener->onLogout(new LogoutEvent($request, null));
    }

    public function testOnLogoutIfHasSession()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('invalidate');

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);
        $request->method('hasSession')->willReturn(true);

        $sessionLogoutListener = new SessionLogoutListener();
        $sessionLogoutListener->onLogout(new LogoutEvent($request, null));
    }
}
