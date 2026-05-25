<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Flow\DataStorage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Flow\DataStorage\SessionDataStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class SessionDataStorageTest extends TestCase
{
    private SessionDataStorage $storage;
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->storage = new SessionDataStorage('test_key', $requestStack);
    }

    public function testSaveAndLoad()
    {
        $data = new \stdClass();
        $data->name = 'John';

        $this->storage->save($data);

        $loaded = $this->storage->load();
        self::assertEquals($data, $loaded);
    }

    public function testLoadReturnsDefaultWhenEmpty()
    {
        $default = new \stdClass();
        $default->name = 'default';

        $loaded = $this->storage->load($default);
        self::assertSame($default, $loaded);
    }

    public function testLoadReturnsCopyDecoupledFromSession()
    {
        $data = new \stdClass();
        $data->name = 'John';
        $data->nested = new \stdClass();
        $data->nested->value = 'original';

        $this->storage->save($data);

        $loaded = $this->storage->load();

        // The loaded data should be equal but not the same instance
        self::assertEquals($data, $loaded);
        self::assertNotSame($data, $loaded);

        // Modifying the loaded data should NOT affect the session data
        $loaded->name = 'Modified';
        $loaded->nested->value = 'modified';

        $reloaded = $this->storage->load();
        self::assertSame('John', $reloaded->name);
        self::assertSame('original', $reloaded->nested->value);
    }

    public function testSaveDecouplesDataFromCallerReference()
    {
        $data = new \stdClass();
        $data->name = 'John';
        $data->nested = new \stdClass();
        $data->nested->value = 'original';

        $this->storage->save($data);

        // Mutating the original object after save should NOT affect stored data
        $data->name = 'Modified';
        $data->nested->value = 'modified';

        $loaded = $this->storage->load();
        self::assertSame('John', $loaded->name);
        self::assertSame('original', $loaded->nested->value);
    }

    public function testClear()
    {
        $data = new \stdClass();
        $data->name = 'John';

        $this->storage->save($data);
        $this->storage->clear();

        $loaded = $this->storage->load();
        self::assertNull($loaded);
    }
}
