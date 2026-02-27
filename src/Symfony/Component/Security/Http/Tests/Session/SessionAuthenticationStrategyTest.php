<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Session;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Csrf\TokenStorage\ClearableTokenStorageInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;

class SessionAuthenticationStrategyTest extends TestCase
{
    public function testSessionIsNotChanged()
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->never())->method('getSession');

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE);
        $strategy->onAuthentication($request, new NullToken());
    }

    public function testUnsupportedStrategy()
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->never())->method('getSession');

        $strategy = new SessionAuthenticationStrategy('foo');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid session authentication strategy "foo"');

        $strategy->onAuthentication($request, new NullToken());
    }

    public function testSessionIsMigrated()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('migrate')->with($this->equalTo(true));

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE);
        $strategy->onAuthentication($this->getRequest($session), new NullToken());
    }

    public function testSessionIsInvalidated()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('invalidate');

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::INVALIDATE);
        $strategy->onAuthentication($this->getRequest($session), new NullToken());
    }

    public function testCsrfTokensAreCleared()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('migrate')->with($this->equalTo(true));

        $csrfStorage = $this->createMock(ClearableTokenStorageInterface::class);
        $csrfStorage->expects($this->once())->method('clear');

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE, $csrfStorage);
        $strategy->onAuthentication($this->getRequest($session), new NullToken());
    }

    private function getRequest($session = null)
    {
        $request = new Request();

        if (null !== $session) {
            $request->setSession($session);
        }

        return $request;
    }
}
