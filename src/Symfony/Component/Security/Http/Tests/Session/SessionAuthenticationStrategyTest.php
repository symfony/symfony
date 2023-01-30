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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\TokenStorage\ClearableTokenStorageInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;

class SessionAuthenticationStrategyTest extends TestCase
{
    public function testSessionIsNotChanged()
    {
        $request = $this->getRequest();
        $request->expects($this->never())->method('getSession');

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE);
        $strategy->onAuthentication($request, $this->createMock(TokenInterface::class));
    }

    public function testUnsupportedStrategy()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid session authentication strategy "foo"');
        $request = $this->getRequest();
        $request->expects($this->never())->method('getSession');

        $strategy = new SessionAuthenticationStrategy('foo');
        $strategy->onAuthentication($request, $this->createMock(TokenInterface::class));
    }

    public function testSessionIsMigrated()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('migrate')->with($this->equalTo(true));

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE);
        $strategy->onAuthentication($this->getRequest($session), $this->createMock(TokenInterface::class));
    }

    public function testSessionIsInvalidated()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('invalidate');

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::INVALIDATE);
        $strategy->onAuthentication($this->getRequest($session), $this->createMock(TokenInterface::class));
    }

    public function testCsrfTokensAreCleared()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('migrate')->with($this->equalTo(true));

        $csrfStorage = $this->createMock(ClearableTokenStorageInterface::class);
        $csrfStorage->expects($this->once())->method('clear');

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE, $csrfStorage);
        $strategy->onAuthentication($this->getRequest($session), $this->createMock(TokenInterface::class));
    }

    private function getRequest($session = null)
    {
        $request = $this->createMock(Request::class);

        if (null !== $session) {
            $request->expects($this->any())->method('getSession')->willReturn($session);
        }

        return $request;
    }
}
