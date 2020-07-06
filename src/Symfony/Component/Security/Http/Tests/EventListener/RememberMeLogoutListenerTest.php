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
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\EventListener\RememberMeLogoutListener;
use Symfony\Component\Security\Http\RememberMe\AbstractRememberMeServices;

class RememberMeLogoutListenerTest extends TestCase
{
    public function testOnLogoutDoesNothingIfNoToken()
    {
        $rememberMeServices = $this->createMock(AbstractRememberMeServices::class);
        $rememberMeServices->expects($this->never())->method('logout');

        $rememberMeLogoutListener = new RememberMeLogoutListener($rememberMeServices);
        $rememberMeLogoutListener->onLogout(new LogoutEvent(new Request(), null));
    }
}
