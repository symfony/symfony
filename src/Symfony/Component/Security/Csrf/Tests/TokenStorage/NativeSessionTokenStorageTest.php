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
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NativeSessionTokenStorageTest extends TestCase
{
    private const SESSION_NAMESPACE = 'foobar';

    /**
     * @var NativeSessionTokenStorage
     */
    private $storage;

    protected function setUp(): void
    {
        $_SESSION = [];

        $this->storage = new NativeSessionTokenStorage(self::SESSION_NAMESPACE);
    }

    public function testStoreTokenInClosedSession()
    {
        $this->storage->setToken('token_id', 'TOKEN');

        self::assertSame([self::SESSION_NAMESPACE => ['token_id' => 'TOKEN']], $_SESSION);
    }

    public function testStoreTokenInClosedSessionWithExistingSessionId()
    {
        session_id('foobar');

        self::assertSame(\PHP_SESSION_NONE, session_status());

        $this->storage->setToken('token_id', 'TOKEN');

        self::assertSame(\PHP_SESSION_ACTIVE, session_status());
        self::assertSame([self::SESSION_NAMESPACE => ['token_id' => 'TOKEN']], $_SESSION);
    }

    public function testStoreTokenInActiveSession()
    {
        session_start();

        $this->storage->setToken('token_id', 'TOKEN');

        self::assertSame([self::SESSION_NAMESPACE => ['token_id' => 'TOKEN']], $_SESSION);
    }

    /**
     * @depends testStoreTokenInClosedSession
     */
    public function testCheckToken()
    {
        self::assertFalse($this->storage->hasToken('token_id'));

        $this->storage->setToken('token_id', 'TOKEN');

        self::assertTrue($this->storage->hasToken('token_id'));
    }

    /**
     * @depends testStoreTokenInClosedSession
     */
    public function testGetExistingToken()
    {
        $this->storage->setToken('token_id', 'TOKEN');

        self::assertSame('TOKEN', $this->storage->getToken('token_id'));
    }

    public function testGetNonExistingToken()
    {
        self::expectException(TokenNotFoundException::class);
        $this->storage->getToken('token_id');
    }

    /**
     * @depends testCheckToken
     */
    public function testRemoveNonExistingToken()
    {
        self::assertNull($this->storage->removeToken('token_id'));
        self::assertFalse($this->storage->hasToken('token_id'));
    }

    /**
     * @depends testCheckToken
     */
    public function testRemoveExistingToken()
    {
        $this->storage->setToken('token_id', 'TOKEN');

        self::assertSame('TOKEN', $this->storage->removeToken('token_id'));
        self::assertFalse($this->storage->hasToken('token_id'));
    }

    public function testClearRemovesAllTokensFromTheConfiguredNamespace()
    {
        $this->storage->setToken('foo', 'bar');
        $this->storage->clear();

        self::assertFalse($this->storage->hasToken('foo'));
        self::assertArrayNotHasKey(self::SESSION_NAMESPACE, $_SESSION);
    }

    public function testClearDoesNotRemoveSessionValuesFromOtherNamespaces()
    {
        $_SESSION['foo']['bar'] = 'baz';
        $this->storage->clear();

        self::assertArrayHasKey('foo', $_SESSION);
        self::assertArrayHasKey('bar', $_SESSION['foo']);
        self::assertSame('baz', $_SESSION['foo']['bar']);
    }

    public function testClearDoesNotRemoveNonNamespacedSessionValues()
    {
        $_SESSION['foo'] = 'baz';
        $this->storage->clear();

        self::assertArrayHasKey('foo', $_SESSION);
        self::assertSame('baz', $_SESSION['foo']);
    }
}
