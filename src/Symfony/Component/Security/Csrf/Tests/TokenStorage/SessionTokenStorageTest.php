<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests\TokenStorage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SessionTokenStorageTest extends TestCase
{
    private const SESSION_NAMESPACE = 'foobar';

    private Session $session;
    private SessionTokenStorage $storage;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->storage = new SessionTokenStorage($requestStack, self::SESSION_NAMESPACE);
    }

    public function testStoreTokenInNotStartedSessionStartsTheSession()
    {
        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertTrue($this->session->isStarted());
    }

    public function testStoreTokenInActiveSession()
    {
        $this->session->start();
        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertSame('TOKEN', $this->session->get(self::SESSION_NAMESPACE.'/token_id'));
    }

    public function testCheckTokenInClosedSession()
    {
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        $this->assertTrue($this->storage->hasToken('token_id'));
        $this->assertTrue($this->session->isStarted());
    }

    public function testCheckTokenInActiveSession()
    {
        $this->session->start();
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        $this->assertTrue($this->storage->hasToken('token_id'));
    }

    public function testGetExistingTokenFromClosedSession()
    {
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        $this->assertSame('RESULT', $this->storage->getToken('token_id'));
        $this->assertTrue($this->session->isStarted());
    }

    public function testGetExistingTokenFromActiveSession()
    {
        $this->session->start();
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        $this->assertSame('RESULT', $this->storage->getToken('token_id'));
    }

    public function testGetNonExistingTokenFromClosedSession()
    {
        $this->expectException(TokenNotFoundException::class);
        $this->storage->getToken('token_id');
    }

    public function testGetNonExistingTokenFromActiveSession()
    {
        $this->expectException(TokenNotFoundException::class);
        $this->session->start();
        $this->storage->getToken('token_id');
    }

    public function testRemoveNonExistingTokenFromClosedSession()
    {
        $this->assertNull($this->storage->removeToken('token_id'));
    }

    public function testRemoveNonExistingTokenFromActiveSession()
    {
        $this->session->start();

        $this->assertNull($this->storage->removeToken('token_id'));
    }

    public function testRemoveExistingTokenFromClosedSession()
    {
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'TOKEN');

        $this->assertSame('TOKEN', $this->storage->removeToken('token_id'));
    }

    public function testRemoveExistingTokenFromActiveSession()
    {
        $this->session->start();
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'TOKEN');

        $this->assertSame('TOKEN', $this->storage->removeToken('token_id'));
    }

    public function testClearRemovesAllTokensFromTheConfiguredNamespace()
    {
        $this->storage->setToken('foo', 'bar');
        $this->storage->clear();

        $this->assertFalse($this->storage->hasToken('foo'));
        $this->assertFalse($this->session->has(self::SESSION_NAMESPACE.'/foo'));
    }

    public function testClearDoesNotRemoveSessionValuesFromOtherNamespaces()
    {
        $this->session->set('foo/bar', 'baz');
        $this->storage->clear();

        $this->assertTrue($this->session->has('foo/bar'));
        $this->assertSame('baz', $this->session->get('foo/bar'));
    }

    public function testClearDoesNotRemoveNonNamespacedSessionValues()
    {
        $this->session->set('foo', 'baz');
        $this->storage->clear();

        $this->assertTrue($this->session->has('foo'));
        $this->assertSame('baz', $this->session->get('foo'));
    }
}
