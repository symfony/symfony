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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Http\Logout\CsrfTokenClearingLogoutHandler;

/**
 * @group legacy
 */
class CsrfTokenClearingLogoutHandlerTest extends TestCase
{
    private $session;
    private $requestStack;
    private $csrfTokenStorage;
    private $csrfTokenClearingLogoutHandler;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());

        // BC for symfony/security-core < 5.3
        if (method_exists(SessionTokenStorage::class, 'getSession')) {
            $request = new Request();
            $request->setSession($this->session);
            $this->requestStack = new RequestStack();
            $this->requestStack->push($request);
        }

        $this->csrfTokenStorage = new SessionTokenStorage($this->requestStack ?? $this->session, 'foo');
        $this->csrfTokenStorage->setToken('foo', 'bar');
        $this->csrfTokenStorage->setToken('foobar', 'baz');
        $this->csrfTokenClearingLogoutHandler = new CsrfTokenClearingLogoutHandler($this->csrfTokenStorage);
    }

    public function testCsrfTokenCookieWithSameNamespaceIsRemoved()
    {
        $this->assertSame('bar', $this->session->get('foo/foo'));
        $this->assertSame('baz', $this->session->get('foo/foobar'));

        $this->csrfTokenClearingLogoutHandler->logout(new Request(), new Response(), $this->createMock(TokenInterface::class));

        $this->assertFalse($this->csrfTokenStorage->hasToken('foo'));
        $this->assertFalse($this->csrfTokenStorage->hasToken('foobar'));

        $this->assertFalse($this->session->has('foo/foo'));
        $this->assertFalse($this->session->has('foo/foobar'));
    }

    public function testCsrfTokenCookieWithDifferentNamespaceIsNotRemoved()
    {
        $barNamespaceCsrfSessionStorage = new SessionTokenStorage($this->requestStack ?? $this->session, 'bar');
        $barNamespaceCsrfSessionStorage->setToken('foo', 'bar');
        $barNamespaceCsrfSessionStorage->setToken('foobar', 'baz');

        $this->assertSame('bar', $this->session->get('foo/foo'));
        $this->assertSame('baz', $this->session->get('foo/foobar'));
        $this->assertSame('bar', $this->session->get('bar/foo'));
        $this->assertSame('baz', $this->session->get('bar/foobar'));

        $this->csrfTokenClearingLogoutHandler->logout(new Request(), new Response(), $this->createMock(TokenInterface::class));

        $this->assertTrue($barNamespaceCsrfSessionStorage->hasToken('foo'));
        $this->assertTrue($barNamespaceCsrfSessionStorage->hasToken('foobar'));
        $this->assertSame('bar', $barNamespaceCsrfSessionStorage->getToken('foo'));
        $this->assertSame('baz', $barNamespaceCsrfSessionStorage->getToken('foobar'));
        $this->assertFalse($this->csrfTokenStorage->hasToken('foo'));
        $this->assertFalse($this->csrfTokenStorage->hasToken('foobar'));

        $this->assertFalse($this->session->has('foo/foo'));
        $this->assertFalse($this->session->has('foo/foobar'));
        $this->assertSame('bar', $this->session->get('bar/foo'));
        $this->assertSame('baz', $this->session->get('bar/foobar'));
    }
}
