<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class ReflectionClassResourceTest extends TestCase
{
    public function testToString()
    {
        $res = new ReflectionClassResource(new \ReflectionClass(\ErrorException::class));

        $this->assertSame('reflection.ErrorException', (string) $res);
    }

    public function testSerializeUnserialize()
    {
        $res = new ReflectionClassResource(new \ReflectionClass(DummyInterface::class));
        $ser = unserialize(serialize($res));

        $this->assertTrue($res->isFresh(0));
        $this->assertTrue($ser->isFresh(0));

        $this->assertSame((string) $res, (string) $ser);
    }

    public function testIsFresh()
    {
        $res = new ReflectionClassResource(new \ReflectionClass(__CLASS__));
        $mtime = filemtime(__FILE__);

        $this->assertTrue($res->isFresh($mtime), '->isFresh() returns true if the resource has not changed in same second');
        $this->assertTrue($res->isFresh($mtime + 10), '->isFresh() returns true if the resource has not changed');
        $this->assertTrue($res->isFresh($mtime - 86400), '->isFresh() returns true if the resource has not changed');
    }

    public function testIsFreshForDeletedResources()
    {
        $now = time();
        $tmp = sys_get_temp_dir().'/tmp.php';
        file_put_contents($tmp, '<?php class ReflectionClassResourceTestClass {}');
        require $tmp;

        $res = new ReflectionClassResource(new \ReflectionClass(\ReflectionClassResourceTestClass::class));
        $this->assertTrue($res->isFresh($now));

        unlink($tmp);
        $this->assertFalse($res->isFresh($now), '->isFresh() returns false if the resource does not exist');
    }

    /**
     * @dataProvider provideHashedSignature
     */
    public function testHashedSignature(bool $changeExpected, int $changedLine, ?string $changedCode, \Closure $setContext = null)
    {
        if ($setContext) {
            $setContext();
        }

        $code = <<<'EOPHP'
/* 0*/
/* 1*/  class %s extends ErrorException
/* 2*/  {
/* 3*/      const FOO = 123;
/* 4*/
/* 5*/      public $pub = [];
/* 6*/
/* 7*/      protected $prot;
/* 8*/
/* 9*/      private $priv;
/*10*/
/*11*/      public function pub($arg = null) {}
/*12*/
/*13*/      protected function prot($a = []) {}
/*14*/
/*15*/      private function priv() {}
/*16*/
/*17*/      public function ccc($bar = A_CONSTANT_THAT_FOR_SURE_WILL_NEVER_BE_DEFINED_CCCCCC) {}
/*18*/  }
EOPHP;

        static $expectedSignature, $generateSignature;

        if (null === $expectedSignature) {
            eval(sprintf($code, $class = 'Foo'.str_replace('.', '_', uniqid('', true))));
            $r = new \ReflectionClass(ReflectionClassResource::class);
            $generateSignature = $r->getMethod('generateSignature');
            $generateSignature = $generateSignature->getClosure($r->newInstanceWithoutConstructor());
            $expectedSignature = implode("\n", iterator_to_array($generateSignature(new \ReflectionClass($class))));
        }

        $code = explode("\n", $code);
        if (null !== $changedCode) {
            $code[$changedLine] = $changedCode;
        }
        eval(sprintf(implode("\n", $code), $class = 'Foo'.str_replace('.', '_', uniqid('', true))));
        $signature = implode("\n", iterator_to_array($generateSignature(new \ReflectionClass($class))));

        if ($changeExpected) {
            $this->assertNotSame($expectedSignature, $signature);
        } else {
            $this->assertSame($expectedSignature, $signature);
        }
    }

    public static function provideHashedSignature(): iterable
    {
        yield [false, 0, "// line change\n\n"];
        yield [true, 0, '/** class docblock */'];
        yield [true, 0, '#[Foo]'];
        yield [true, 0, '#[Foo(new MissingClass)]'];
        yield [true, 1, 'abstract class %s'];
        yield [true, 1, 'final class %s'];
        yield [true, 1, 'class %s extends Exception'];
        yield [true, 1, 'class %s implements '.DummyInterface::class];
        yield [true, 3, 'const FOO = 456;'];
        yield [true, 3, 'const BAR = 123;'];
        yield [true, 4, '/** pub docblock */'];
        yield [true, 5, 'protected $pub = [];'];
        yield [true, 5, 'public $pub = [123];'];
        yield [true, 5, '#[Foo(new MissingClass)] public $pub = [];'];
        yield [true, 6, '/** prot docblock */'];
        yield [true, 7, 'private $prot;'];
        yield [false, 8, '/** priv docblock */'];
        yield [false, 9, 'private $priv = 123;'];
        yield [true, 10, '/** pub docblock */'];
        yield [true, 11, 'public function pub(...$arg) {}'];
        yield [true, 11, 'public function pub($arg = null): Foo {}'];
        yield [false, 11, "public function pub(\$arg = null) {\nreturn 123;\n}"];
        yield [true, 12, '/** prot docblock */'];
        yield [true, 13, 'protected function prot($a = [123]) {}'];
        yield [true, 13, '#[Foo] protected function prot($a = []) {}'];
        yield [true, 13, 'protected function prot(#[Foo] $a = []) {}'];
        yield [true, 13, '#[Foo(new MissingClass)] protected function prot($a = []) {}'];
        yield [true, 13, 'protected function prot(#[Foo(new MissingClass)] $a = []) {}'];
        yield [false, 14, '/** priv docblock */'];
        yield [false, 15, ''];

        // PHP7.4 typed properties without default value are
        // undefined, make sure this doesn't throw an error
        yield [true, 5, 'public array $pub;'];
        yield [false, 7, 'protected int $prot;'];
        yield [false, 9, 'private string $priv;'];
        yield [true, 17, 'public function __construct(private $bar = new \stdClass()) {}'];
        yield [true, 17, 'public function ccc($bar = new \stdClass()) {}'];
        yield [true, 17, 'public function ccc($bar = new MissingClass()) {}'];
        yield [true, 17, 'public function ccc($bar = 187) {}'];
        yield [true, 17, 'public function ccc($bar = ANOTHER_ONE_THAT_WILL_NEVER_BE_DEFINED_CCCCCCCCC) {}'];
        yield [true, 17, 'public function ccc($bar = parent::BOOM) {}'];
        yield [false, 17, null, static function () { \define('A_CONSTANT_THAT_FOR_SURE_WILL_NEVER_BE_DEFINED_CCCCCC', 'foo'); }];
    }

    public function testEventSubscriber()
    {
        $res = new ReflectionClassResource(new \ReflectionClass(TestEventSubscriber::class));
        $this->assertTrue($res->isFresh(0));

        TestEventSubscriber::$subscribedEvents = [123];
        $this->assertFalse($res->isFresh(0));

        $res = new ReflectionClassResource(new \ReflectionClass(TestEventSubscriber::class));
        $this->assertTrue($res->isFresh(0));
    }

    /**
     * @group legacy
     */
    public function testMessageSubscriber()
    {
        $res = new ReflectionClassResource(new \ReflectionClass(TestMessageSubscriber::class));
        $this->assertTrue($res->isFresh(0));

        TestMessageSubscriberConfigHolder::$handledMessages = ['SomeMessageClass' => []];
        $this->assertFalse($res->isFresh(0));

        $res = new ReflectionClassResource(new \ReflectionClass(TestMessageSubscriber::class));
        $this->assertTrue($res->isFresh(0));

        TestMessageSubscriberConfigHolder::$handledMessages = ['OtherMessageClass' => []];
        $this->assertFalse($res->isFresh(0));

        $res = new ReflectionClassResource(new \ReflectionClass(TestMessageSubscriber::class));
        $this->assertTrue($res->isFresh(0));
    }

    public function testServiceSubscriber()
    {
        $res = new ReflectionClassResource(new \ReflectionClass(TestServiceSubscriber::class));
        $this->assertTrue($res->isFresh(0));

        TestServiceSubscriber::$subscribedServices = [123];
        $this->assertFalse($res->isFresh(0));

        $res = new ReflectionClassResource(new \ReflectionClass(TestServiceSubscriber::class));
        $this->assertTrue($res->isFresh(0));
    }

    public function testIgnoresObjectsInSignature()
    {
        $res = new ReflectionClassResource(new \ReflectionClass(TestServiceWithStaticProperty::class));
        $this->assertTrue($res->isFresh(0));

        TestServiceWithStaticProperty::$initializedObject = new TestServiceWithStaticProperty();
        $this->assertTrue($res->isFresh(0));
    }
}

interface DummyInterface
{
}

class TestEventSubscriber implements EventSubscriberInterface
{
    public static $subscribedEvents = [];

    public static function getSubscribedEvents(): array
    {
        return self::$subscribedEvents;
    }
}

if (interface_exists(MessageSubscriberInterface::class)) {
    class TestMessageSubscriber implements MessageSubscriberInterface
    {
        public static function getHandledMessages(): iterable
        {
            foreach (TestMessageSubscriberConfigHolder::$handledMessages as $key => $subscribedMessage) {
                yield $key => $subscribedMessage;
            }
        }
    }
    class TestMessageSubscriberConfigHolder
    {
        public static array $handledMessages = [];
    }
}

class TestServiceSubscriber implements ServiceSubscriberInterface
{
    public static array $subscribedServices = [];

    public static function getSubscribedServices(): array
    {
        return self::$subscribedServices;
    }
}

class TestServiceWithStaticProperty
{
    public static object $initializedObject;
}
