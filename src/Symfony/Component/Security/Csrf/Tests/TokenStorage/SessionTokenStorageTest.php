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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
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
    use ExpectDeprecationTrait;

    private const SESSION_NAMESPACE = 'foobar';

    /**
     * @var Session
     */
    private $session;

    /**
     * @var SessionTokenStorage
     */
    private $storage;

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

        self::assertTrue($this->session->isStarted());
    }

    public function testStoreTokenInActiveSession()
    {
        $this->session->start();
        $this->storage->setToken('token_id', 'TOKEN');

        self::assertSame('TOKEN', $this->session->get(self::SESSION_NAMESPACE.'/token_id'));
    }

    public function testCheckTokenInClosedSession()
    {
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        self::assertTrue($this->storage->hasToken('token_id'));
        self::assertTrue($this->session->isStarted());
    }

    public function testCheckTokenInActiveSession()
    {
        $this->session->start();
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        self::assertTrue($this->storage->hasToken('token_id'));
    }

    public function testGetExistingTokenFromClosedSession()
    {
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        self::assertSame('RESULT', $this->storage->getToken('token_id'));
        self::assertTrue($this->session->isStarted());
    }

    public function testGetExistingTokenFromActiveSession()
    {
        $this->session->start();
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'RESULT');

        self::assertSame('RESULT', $this->storage->getToken('token_id'));
    }

    public function testGetNonExistingTokenFromClosedSession()
    {
        self::expectException(TokenNotFoundException::class);
        $this->storage->getToken('token_id');
    }

    public function testGetNonExistingTokenFromActiveSession()
    {
        self::expectException(TokenNotFoundException::class);
        $this->session->start();
        $this->storage->getToken('token_id');
    }

    public function testRemoveNonExistingTokenFromClosedSession()
    {
        self::assertNull($this->storage->removeToken('token_id'));
    }

    public function testRemoveNonExistingTokenFromActiveSession()
    {
        $this->session->start();

        self::assertNull($this->storage->removeToken('token_id'));
    }

    public function testRemoveExistingTokenFromClosedSession()
    {
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'TOKEN');

        self::assertSame('TOKEN', $this->storage->removeToken('token_id'));
    }

    public function testRemoveExistingTokenFromActiveSession()
    {
        $this->session->start();
        $this->session->set(self::SESSION_NAMESPACE.'/token_id', 'TOKEN');

        self::assertSame('TOKEN', $this->storage->removeToken('token_id'));
    }

    public function testClearRemovesAllTokensFromTheConfiguredNamespace()
    {
        $this->storage->setToken('foo', 'bar');
        $this->storage->clear();

        self::assertFalse($this->storage->hasToken('foo'));
        self::assertFalse($this->session->has(self::SESSION_NAMESPACE.'/foo'));
    }

    public function testClearDoesNotRemoveSessionValuesFromOtherNamespaces()
    {
        $this->session->set('foo/bar', 'baz');
        $this->storage->clear();

        self::assertTrue($this->session->has('foo/bar'));
        self::assertSame('baz', $this->session->get('foo/bar'));
    }

    public function testClearDoesNotRemoveNonNamespacedSessionValues()
    {
        $this->session->set('foo', 'baz');
        $this->storage->clear();

        self::assertTrue($this->session->has('foo'));
        self::assertSame('baz', $this->session->get('foo'));
    }

    /**
     * @group legacy
     */
    public function testMockSessionIsCreatedWhenMissing()
    {
        $this->expectDeprecation('Since symfony/security-csrf 5.3: Using the "Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage" without a session has no effect and is deprecated. It will throw a "Symfony\Component\HttpFoundation\Exception\SessionNotFoundException" in Symfony 6.0');

        $this->storage->setToken('token_id', 'TOKEN');

        $requestStack = new RequestStack();
        $storage = new SessionTokenStorage($requestStack, self::SESSION_NAMESPACE);

        self::assertFalse($storage->hasToken('foo'));
        $storage->setToken('foo', 'bar');
        self::assertTrue($storage->hasToken('foo'));
        self::assertSame('bar', $storage->getToken('foo'));

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);
    }

    /**
     * @group legacy
     */
    public function testMockSessionIsReusedEvenWhenRequestHasSession()
    {
        $this->expectDeprecation('Since symfony/security-csrf 5.3: Using the "Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage" without a session has no effect and is deprecated. It will throw a "Symfony\Component\HttpFoundation\Exception\SessionNotFoundException" in Symfony 6.0');

        $this->storage->setToken('token_id', 'TOKEN');

        $requestStack = new RequestStack();
        $storage = new SessionTokenStorage($requestStack, self::SESSION_NAMESPACE);

        $storage->setToken('foo', 'bar');
        self::assertSame('bar', $storage->getToken('foo'));

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        self::assertSame('bar', $storage->getToken('foo'));
    }
}
