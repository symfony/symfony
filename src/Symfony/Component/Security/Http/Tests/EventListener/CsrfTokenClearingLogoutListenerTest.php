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
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\EventListener\CsrfTokenClearingLogoutListener;

class CsrfTokenClearingLogoutListenerTest extends TestCase
{
    public function testSkipsClearingSessionTokenStorageOnStatelessRequest()
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
}
