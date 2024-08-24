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
        try {
            (new CsrfTokenClearingLogoutListener(
                new SessionTokenStorage(new RequestStack())
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

        try {
            (new CsrfTokenClearingLogoutListener(
                new SessionTokenStorage(new RequestStack())
            ))->onLogout(new LogoutEvent($request, null));
        } catch (SessionNotFoundException) {
            $this->fail('clear() must not be called if the request is stateless');
        }

        $this->addToAssertionCount(1);
    }
}
