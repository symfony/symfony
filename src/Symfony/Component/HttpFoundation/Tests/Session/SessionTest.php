<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionBagProxy;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * SessionTest.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Drak <drak@zikula.org>
 */
class SessionTest extends TestCase
{
    protected SessionStorageInterface $storage;
    protected SessionInterface $session;

    protected function setUp(): void
    {
        $this->storage = new MockArraySessionStorage();
        $this->session = new Session($this->storage, new AttributeBag(), new FlashBag());
    }

    public function testStart()
    {
        $this->assertEquals('', $this->session->getId());
        $this->assertTrue($this->session->start());
        $this->assertNotEquals('', $this->session->getId());
    }

    public function testIsStarted()
    {
        $this->assertFalse($this->session->isStarted());
        $this->session->start();
        $this->assertTrue($this->session->isStarted());
    }

    public function testSetId()
    {
        $this->assertEquals('', $this->session->getId());
        $this->session->setId('0123456789abcdef');
        $this->session->start();
        $this->assertEquals('0123456789abcdef', $this->session->getId());
    }

    public function testSetIdAfterStart()
    {
        $this->session->start();
        $id = $this->session->getId();

        $e = null;
        try {
            $this->session->setId($id);
        } catch (\Exception $e) {
        }

        $this->assertNull($e);

        try {
            $this->session->setId('different');
        } catch (\Exception $e) {
        }

        $this->assertInstanceOf(\LogicException::class, $e);
    }

    public function testSetName()
    {
        $this->assertEquals('MOCKSESSID', $this->session->getName());
        $this->session->setName('session.test.com');
        $this->session->start();
        $this->assertEquals('session.test.com', $this->session->getName());
    }

    public function testGet()
    {
        // tests defaults
        $this->assertNull($this->session->get('foo'));
        $this->assertEquals(1, $this->session->get('foo', 1));
    }

    #[DataProvider('setProvider')]
    public function testSet($key, $value, $result)
    {
        $this->session->set($key, $value);
        $this->assertEquals($value, $this->session->get($key));
    }

    #[DataProvider('setProvider')]
    public function testHas($key, $value, $result)
    {
        $this->session->set($key, $value);
        $this->assertTrue($this->session->has($key));
        $this->assertFalse($this->session->has($key.'non_value'));
    }

    public function testReplace()
    {
        $this->session->replace(['happiness' => 'be good', 'symfony' => 'awesome']);
        $this->assertEquals(['happiness' => 'be good', 'symfony' => 'awesome'], $this->session->all());
        $this->session->replace([]);
        $this->assertEquals([], $this->session->all());
    }

    #[DataProvider('setProvider')]
    public function testAll($key, $value, $result)
    {
        $this->session->set($key, $value);
        $this->assertEquals($result, $this->session->all());
    }

    #[DataProvider('setProvider')]
    public function testClear($key, $value, $result)
    {
        $this->session->set('hi', 'fabien');
        $this->session->set($key, $value);
        $this->session->clear();
        $this->assertEquals([], $this->session->all());
    }

    public static function setProvider()
    {
        return [
            ['foo', 'bar', ['foo' => 'bar']],
            ['foo.bar', 'too much beer', ['foo.bar' => 'too much beer']],
            ['great', 'symfony is great', ['great' => 'symfony is great']],
        ];
    }

    #[DataProvider('setProvider')]
    public function testRemove($key, $value, $result)
    {
        $this->session->set('hi.world', 'have a nice day');
        $this->session->set($key, $value);
        $this->session->remove($key);
        $this->assertEquals(['hi.world' => 'have a nice day'], $this->session->all());
    }

    public function testInvalidate()
    {
        $this->session->set('invalidate', 123);
        $this->session->invalidate();
        $this->assertEquals([], $this->session->all());
    }

    public function testMigrate()
    {
        $this->session->set('migrate', 321);
        $this->session->migrate();
        $this->assertEquals(321, $this->session->get('migrate'));
    }

    public function testMigrateDestroy()
    {
        $this->session->set('migrate', 333);
        $this->session->migrate(true);
        $this->assertEquals(333, $this->session->get('migrate'));
    }

    public function testSave()
    {
        $this->session->start();
        $this->session->save();

        $this->assertFalse($this->session->isStarted());
    }

    public function testGetId()
    {
        $this->assertEquals('', $this->session->getId());
        $this->session->start();
        $this->assertNotEquals('', $this->session->getId());
    }

    public function testGetFlashBag()
    {
        $this->assertInstanceOf(FlashBagInterface::class, $this->session->getFlashBag());
    }

    public function testGetIterator()
    {
        $attributes = ['hello' => 'world', 'symfony' => 'rocks'];
        foreach ($attributes as $key => $val) {
            $this->session->set($key, $val);
        }

        $i = 0;
        foreach ($this->session as $key => $val) {
            $this->assertEquals($attributes[$key], $val);
            ++$i;
        }

        $this->assertEquals(\count($attributes), $i);
    }

    public function testGetCount()
    {
        $this->session->set('hello', 'world');
        $this->session->set('symfony', 'rocks');

        $this->assertCount(2, $this->session);
    }

    public function testGetMeta()
    {
        $this->assertInstanceOf(MetadataBag::class, $this->session->getMetadataBag());
    }

    public function testIsEmpty()
    {
        $this->assertTrue($this->session->isEmpty());

        $this->session->set('hello', 'world');
        $this->assertFalse($this->session->isEmpty());

        $this->session->remove('hello');
        $this->assertTrue($this->session->isEmpty());

        $flash = $this->session->getFlashBag();
        $flash->set('hello', 'world');
        $this->assertFalse($this->session->isEmpty());

        $flash->get('hello');
        $this->assertTrue($this->session->isEmpty());
    }

    public function testGetBagWithBagImplementingGetBag()
    {
        $bag = new AttributeBag();
        $bag->setName('foo');

        $storage = new MockArraySessionStorage();
        $storage->registerBag($bag);

        $this->assertSame($bag, (new Session($storage))->getBag('foo'));
    }

    public function testGetBagWithBagNotImplementingGetBag()
    {
        $data = [];

        $bag = new AttributeBag();
        $bag->setName('foo');

        $storage = new MockArraySessionStorage();
        $storage->registerBag(new SessionBagProxy($bag, $data, $usageIndex, null));

        $this->assertSame($bag, (new Session($storage))->getBag('foo'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSaveReportsChangesMadeWithoutCallingSet()
    {
        $session = new Session(new MockArraySessionStorage(), new AttributeBag('_sf2_attributes', false, true));

        $object = new \stdClass();
        $object->foo = 'bar';
        $session->set('set_then_changed', $object);
        $object->foo = 'baz';

        $session->set('nested', ['list' => [new \stdClass()]]);
        $session->get('nested')['list'][0]->foo = 'bar';

        $session->set('array_object', new \ArrayObject([1]));
        $session->get('array_object')[] = 2;

        $this->expectUserDeprecationMessage('Since symfony/http-foundation 8.2: Saving changes made to the value of session attribute "set_then_changed" without calling "set()" afterwards is deprecated; call "set()" with the changed value instead.');
        $this->expectUserDeprecationMessage('Since symfony/http-foundation 8.2: Saving changes made to the value of session attribute "nested" without calling "set()" afterwards is deprecated; call "set()" with the changed value instead.');
        $this->expectUserDeprecationMessage('Since symfony/http-foundation 8.2: Saving changes made to the value of session attribute "array_object" without calling "set()" afterwards is deprecated; call "set()" with the changed value instead.');

        $session->save();
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSaveReportsChangesMadeToValuesReadFromStorage()
    {
        $object = new \stdClass();
        $object->foo = 'bar';
        $storage = new MockArraySessionStorage();
        $storage->setSessionData(['_sf2_attributes' => ['obj' => $object]]);
        $session = new Session($storage, new AttributeBag('_sf2_attributes', false, true));

        $session->all()['obj']->foo = 'baz';

        $this->expectUserDeprecationMessage('Since symfony/http-foundation 8.2: Saving changes made to the value of session attribute "obj" without calling "set()" afterwards is deprecated; call "set()" with the changed value instead.');

        $session->save();
    }

    #[DataProvider('provideSessionsThatDoNotReportChanges')]
    public function testSaveDoesNotReportChanges(AttributeBag $attributes, bool $changed)
    {
        $session = new Session(new MockArraySessionStorage(), $attributes);

        $object = new \stdClass();
        $object->foo = 'bar';
        $object->nan = \NAN;
        $session->set('obj', $object);
        $session->set('removed', $object);
        $session->set('untouched', new \stdClass());
        $session->get('untouched');

        $object->foo = 'baz';
        $session->remove('removed');
        if (!$changed) {
            $session->set('obj', $object);
        }

        $deprecations = [];
        set_error_handler(static function (int $type, string $message) use (&$deprecations) {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $session->save();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }

    public static function provideSessionsThatDoNotReportChanges(): iterable
    {
        yield 'set after change' => [new AttributeBag('_sf2_attributes', false, true), false];
        yield 'not in debug mode' => [new AttributeBag(), true];
        yield 'isolated values' => [new AttributeBag('_sf2_attributes', true, true), true];
    }
}
