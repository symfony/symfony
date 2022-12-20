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
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;

class SessionAuthenticationStrategyTest extends TestCase
{
    public function testSessionIsNotChanged()
    {
        $request = $this->getRequest();
        $request->expects(self::never())->method('getSession');

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::NONE);
        $strategy->onAuthentication($request, self::createMock(TokenInterface::class));
    }

    public function testUnsupportedStrategy()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Invalid session authentication strategy "foo"');
        $request = $this->getRequest();
        $request->expects(self::never())->method('getSession');

        $strategy = new SessionAuthenticationStrategy('foo');
        $strategy->onAuthentication($request, self::createMock(TokenInterface::class));
    }

    public function testSessionIsMigrated()
    {
        $session = self::createMock(SessionInterface::class);
        $session->expects(self::once())->method('migrate')->with(self::equalTo(true));

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE);
        $strategy->onAuthentication($this->getRequest($session), self::createMock(TokenInterface::class));
    }

    public function testSessionIsInvalidated()
    {
        $session = self::createMock(SessionInterface::class);
        $session->expects(self::once())->method('invalidate');

        $strategy = new SessionAuthenticationStrategy(SessionAuthenticationStrategy::INVALIDATE);
        $strategy->onAuthentication($this->getRequest($session), self::createMock(TokenInterface::class));
    }

    private function getRequest($session = null)
    {
        $request = self::createMock(Request::class);

        if (null !== $session) {
            $request->expects(self::any())->method('getSession')->willReturn($session);
        }

        return $request;
    }
}
