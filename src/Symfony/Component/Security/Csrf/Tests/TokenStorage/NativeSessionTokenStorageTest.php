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
 *
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

        $this->assertSame([self::SESSION_NAMESPACE => ['token_id' => 'TOKEN']], $_SESSION);
    }

    public function testStoreTokenInClosedSessionWithExistingSessionId()
    {
        session_id('foobar');

        $this->assertSame(\PHP_SESSION_NONE, session_status());

        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertSame(\PHP_SESSION_ACTIVE, session_status());
        $this->assertSame([self::SESSION_NAMESPACE => ['token_id' => 'TOKEN']], $_SESSION);
    }

    public function testStoreTokenInActiveSession()
    {
        session_start();

        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertSame([self::SESSION_NAMESPACE => ['token_id' => 'TOKEN']], $_SESSION);
    }

    /**
     * @depends testStoreTokenInClosedSession
     */
    public function testCheckToken()
    {
        $this->assertFalse($this->storage->hasToken('token_id'));

        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertTrue($this->storage->hasToken('token_id'));
    }

    /**
     * @depends testStoreTokenInClosedSession
     */
    public function testGetExistingToken()
    {
        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertSame('TOKEN', $this->storage->getToken('token_id'));
    }

    public function testGetNonExistingToken()
    {
        $this->expectException(TokenNotFoundException::class);
        $this->storage->getToken('token_id');
    }

    /**
     * @depends testCheckToken
     */
    public function testRemoveNonExistingToken()
    {
        $this->assertNull($this->storage->removeToken('token_id'));
        $this->assertFalse($this->storage->hasToken('token_id'));
    }

    /**
     * @depends testCheckToken
     */
    public function testRemoveExistingToken()
    {
        $this->storage->setToken('token_id', 'TOKEN');

        $this->assertSame('TOKEN', $this->storage->removeToken('token_id'));
        $this->assertFalse($this->storage->hasToken('token_id'));
    }

    public function testClearRemovesAllTokensFromTheConfiguredNamespace()
    {
        $this->storage->setToken('foo', 'bar');
        $this->storage->clear();

        $this->assertFalse($this->storage->hasToken('foo'));
        $this->assertArrayNotHasKey(self::SESSION_NAMESPACE, $_SESSION);
    }

    public function testClearDoesNotRemoveSessionValuesFromOtherNamespaces()
    {
        $_SESSION['foo']['bar'] = 'baz';
        $this->storage->clear();

        $this->assertArrayHasKey('foo', $_SESSION);
        $this->assertArrayHasKey('bar', $_SESSION['foo']);
        $this->assertSame('baz', $_SESSION['foo']['bar']);
    }

    public function testClearDoesNotRemoveNonNamespacedSessionValues()
    {
        $_SESSION['foo'] = 'baz';
        $this->storage->clear();

        $this->assertArrayHasKey('foo', $_SESSION);
        $this->assertSame('baz', $_SESSION['foo']);
    }
}
