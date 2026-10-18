<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Test class for NullSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 */
#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
class NullSessionHandlerTest extends TestCase
{
    public function testSaveHandlers()
    {
        $this->getStorage();
        $this->assertEquals('user', \ini_get('session.save_handler'));
    }

    public function testSession()
    {
        session_id('nullsessionstorage');
        $storage = $this->getStorage();
        $session = new Session($storage);
        $this->assertNull($session->get('something'));
        $session->set('something', 'unique');
        $this->assertEquals('unique', $session->get('something'));
    }

    public function testNothingIsPersisted()
    {
        session_id('nullsessionstorage');
        $storage = $this->getStorage();
        $session = new Session($storage);
        $session->start();
        $this->assertEquals('nullsessionstorage', $session->getId());
        $this->assertNull($session->get('something'));
    }

    public function testUpdateTimestampWithEmptyDataDestroysSession()
    {
        $handler = new class extends NullSessionHandler {
            public array $destroyedIds = [];

            public function destroy(#[\SensitiveParameter] string $sessionId): bool
            {
                $this->destroyedIds[] = $sessionId;

                return parent::destroy($sessionId);
            }
        };

        $handler->open('', 'sid');
        $this->assertSame('', $handler->read('id'));

        $this->assertTrue($handler->updateTimestamp('id', 'data'));
        $this->assertSame([], $handler->destroyedIds);

        $this->assertTrue($handler->updateTimestamp('id', ''));
        $this->assertSame(['id'], $handler->destroyedIds);
    }

    public function getStorage()
    {
        return new NativeSessionStorage([], new NullSessionHandler());
    }
}
