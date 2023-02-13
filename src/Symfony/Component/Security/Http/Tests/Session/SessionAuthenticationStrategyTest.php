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
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

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

    public function testCsrfTokensWillBeCleared()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('migrate')->with($this->equalTo(true));

        $request = $this->getRequest($session);

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE);
        $strategy->onAuthentication($request, $this->createMock(TokenInterface::class));

        $this->assertTrue($request->attributes->get(SessionAuthenticationStrategyInterface::CLEAR_CSRF_STORAGE_ATTR_NAME));
    }

    private function getRequest($session = null)
    {
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();

        if (null !== $session) {
            $request->expects($this->any())->method('getSession')->willReturn($session);
        }

        return $request;
    }
}
