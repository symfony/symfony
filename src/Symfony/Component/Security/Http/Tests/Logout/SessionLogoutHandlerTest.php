<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Logout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Logout\SessionLogoutHandler;

/**
 * @group legacy
 */
class SessionLogoutHandlerTest extends TestCase
{
    public function testLogout()
    {
        $handler = new SessionLogoutHandler();

        $request = $this->createMock(Request::class);
        $response = new Response();
        $session = $this->createMock(Session::class);

        $request
            ->expects($this->once())
            ->method('getSession')
            ->willReturn($session)
        ;

        $session
            ->expects($this->once())
            ->method('invalidate')
        ;

        $handler->logout($request, $response, $this->createMock(TokenInterface::class));
    }
}
