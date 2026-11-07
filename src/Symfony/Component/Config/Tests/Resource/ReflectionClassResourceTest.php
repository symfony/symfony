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

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\Config\Tests\Fixtures\FakeVendor\Base;
use Symfony\Component\Config\Tests\Fixtures\LegacyResource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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

    public function testIsFreshComparesSignaturesOfFilesModifiedInSameSecond()
    {
        $tmp = sys_get_temp_dir().'/tmp_same_second.php';
        file_put_contents($tmp, '<?php class ReflectionClassResourceSameSecondTestClass {}');
        touch($tmp, $mtime = time() - 10);
        require $tmp;

        $requestTime = $_SERVER['REQUEST_TIME'] ?? null;
        $_SERVER['REQUEST_TIME'] = time();
        $res = unserialize(serialize(new ReflectionClassResource(new \ReflectionClass(\ReflectionClassResourceSameSecondTestClass::class))));
        // simulates a signature that changed after the resource was created
        (new \ReflectionProperty(ReflectionClassResource::class, 'hash'))->setValue($res, 'outdated');

        try {
            $this->assertTrue($res->isFresh($mtime + 1), '->isFresh() returns true if the file has not changed since the previous second');
            $this->assertFalse($res->isFresh($mtime), '->isFresh() returns false if the signature changed in the same second');
        } finally {
            unlink($tmp);
            if (null === $requestTime) {
                unset($_SERVER['REQUEST_TIME']);
            } else {
                $_SERVER['REQUEST_TIME'] = $requestTime;
            }
        }
    }

    #[DataProvider('provideHashedSignature')]
    public function testHashedSignature(bool $changeExpected, int $changedLine, ?string $changedCode, int $resourceClassNameSuffix, ?\Closure $setContext = null)
    {
        if ($setContext) {
            $setContext();
        }

        $code = <<<'EOPHP'
            /* 0*/
            /* 1*/  class %s extends %s
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

        static $expectedSignature, $signatureGenerator;

        if (null === $expectedSignature) {
            eval(\sprintf($code, $class = 'Foo'.(string) $resourceClassNameSuffix, Base::class));

            $r = new \ReflectionClass(ReflectionClassResource::class);
            $generateSignature = $r->getMethod('generateSignature');

            $res = new ReflectionClassResource(new \ReflectionClass($class), [\dirname(__DIR__).'/Fixtures/FakeVendor']);
            $signatureGenerator = $generateSignature->getClosure($res);
            $expectedSignature = implode("\n", iterator_to_array($signatureGenerator(new \ReflectionClass($class))));

            $signatureGenerator = $generateSignature->getClosure(unserialize(serialize($res)));
        }

        $code = explode("\n", $code);
        if (null !== $changedCode) {
            $code[$changedLine] = $changedCode;
        }
        eval(\sprintf(implode("\n", $code), $class = 'Bar'.(string) $resourceClassNameSuffix, Base::class));
        $signature = implode("\n", iterator_to_array($signatureGenerator(new \ReflectionClass($class))));

        if ($changeExpected) {
            $this->assertNotSame($expectedSignature, $signature);
        } else {
            $this->assertSame($expectedSignature, $signature);
        }
    }

    public static function provideHashedSignature(): iterable
    {
        $i = 0;
        yield [false, 0, "// line change\n\n", ++$i];
        yield [true, 0, '/** class docblock */', ++$i];
        yield [true, 0, '#[Foo]', ++$i];
        yield [true, 0, '#[Foo(new MissingClass)]', ++$i];
        yield [true, 1, 'abstract class %s', ++$i];
        yield [true, 1, 'final class %s', ++$i];
        yield [true, 1, 'class %s extends Exception', ++$i];
        yield [true, 1, 'class %s implements '.DummyInterface::class, ++$i];
        yield [true, 3, 'const FOO = 456;', ++$i];
        yield [true, 3, 'const BAR = 123;', ++$i];
        yield [true, 4, '/** pub docblock */', ++$i];
        yield [true, 5, 'protected $pub = [];', ++$i];
        yield [true, 5, 'public $pub = [123];', ++$i];
        yield [true, 5, '#[Foo(new MissingClass)] public $pub = [];', ++$i];
        yield [true, 6, '/** prot docblock */', ++$i];
        yield [true, 7, 'private $prot;', ++$i];
        yield [false, 8, '/** priv docblock */', ++$i];
        yield [false, 9, 'private $priv = 123;', ++$i];
        yield [true, 10, '/** pub docblock */', ++$i];
        yield [true, 11, 'public function pub(...$arg) {}', ++$i];
        yield [true, 11, 'public function pub($arg = null): Foo {}', ++$i];
        yield [false, 11, "public function pub(\$arg = null) {\nreturn 123;\n}", ++$i];
        yield [true, 12, '/** prot docblock */', ++$i];
        yield [true, 13, 'protected function prot($a = [123]) {}', ++$i];
        yield [true, 13, '#[Foo] protected function prot($a = []) {}', ++$i];
        yield [true, 13, 'protected function prot(#[Foo] $a = []) {}', ++$i];
        yield [true, 13, '#[Foo(new MissingClass)] protected function prot($a = []) {}', ++$i];
        yield [true, 13, 'protected function prot(#[Foo(new MissingClass)] $a = []) {}', ++$i];
        yield [false, 14, '/** priv docblock */', ++$i];
        yield [false, 15, null, ++$i];

        // PHP7.4 typed properties without default value are
        // undefined, make sure this doesn't throw an error
        yield [true, 5, 'public array $pub;', ++$i];
        yield [false, 7, 'protected int $prot;', ++$i];
        yield [false, 9, 'private string $priv;', ++$i];
        yield [true, 17, 'public function __construct(private $bar = new \stdClass()) {}', ++$i];
        yield [true, 17, 'public function ccc($bar = new \stdClass()) {}', ++$i];
        yield [true, 17, 'public function ccc($bar = new MissingClass()) {}', ++$i];
        yield [true, 17, 'public function ccc($bar = 187) {}', ++$i];
        yield [true, 17, 'public function ccc($bar = ANOTHER_ONE_THAT_WILL_NEVER_BE_DEFINED_CCCCCCCCC) {}', ++$i];
        yield [true, 17, 'public function ccc($bar = parent::BOOM) {}', ++$i];
        yield [false, 17, null, ++$i, static function () { \define('A_CONSTANT_THAT_FOR_SURE_WILL_NEVER_BE_DEFINED_CCCCCC', 'foo'); }];
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

    public function testEnum()
    {
        $res = new ReflectionClassResource($enum = new \ReflectionClass(SomeEnum::class));
        $r = new \ReflectionClass(ReflectionClassResource::class);
        $generateSignature = $r->getMethod('generateSignature')->getClosure($res);
        $actual = implode("\n", iterator_to_array($generateSignature($enum)));
        $this->assertStringContainsString('UnitEnum', $actual);
        $this->assertStringContainsString('TestAttribute', $actual);
        $this->assertStringContainsString('Beta', $actual);
    }

    public function testBackedEnum()
    {
        $res = new ReflectionClassResource($enum = new \ReflectionClass(SomeBackedEnum::class));
        $r = new \ReflectionClass(ReflectionClassResource::class);
        $generateSignature = $r->getMethod('generateSignature')->getClosure($res);
        $actual = implode("\n", iterator_to_array($generateSignature($enum)));
        $this->assertStringContainsString('UnitEnum', $actual);
        $this->assertStringContainsString('BackedEnum', $actual);
        $this->assertStringContainsString('TestAttribute', $actual);
        $this->assertStringContainsString('Beta', $actual);
        $this->assertStringContainsString('beta', $actual);
    }

    #[DataProvider('provideRequestTimes')]
    public function testRequestBoundaryIsCapturedWithoutExecutingCallbacks(mixed $requestTime, int $offset)
    {
        $server = $_SERVER;
        unset($_SERVER['SOURCE_DATE_EPOCH'], $_SERVER['REQUEST_TIME']);
        if (null !== $requestTime) {
            $_SERVER['REQUEST_TIME'] = \is_int($requestTime) && 0 < $requestTime ? time() + $requestTime : $requestTime;
        }
        $subscriber = new class implements ServiceSubscriberInterface {
            public static int $calls = 0;

            /**
             * Counts lazy signature callbacks while returning no subscribed services.
             *
             * @return array{}
             */
            public static function getSubscribedServices(): array
            {
                ++self::$calls;

                return [];
            }
        };
        $subscriber::$calls = 0;
        $now = time();
        try {
            $resource = new ReflectionClassResource(new \ReflectionClass($subscriber));
            self::assertSame(0, $subscriber::$calls);
            $serialized = $resource->__serialize();
            self::assertSame(1, $subscriber::$calls);
            self::assertGreaterThanOrEqual($now + $offset, $serialized['loadedAt']);
            self::assertLessThanOrEqual(time(), $serialized['loadedAt']);
            self::assertSame(serialize($resource), serialize(unserialize(serialize($resource))));
        } finally {
            $_SERVER = $server;
        }
    }

    public static function provideRequestTimes(): iterable
    {
        yield 'missing' => [null, 0];
        yield 'string' => ['invalid', 0];
        yield 'numeric string' => ['1700000000', 0];
        yield 'zero' => [0, 0];
        yield 'negative' => [-1, 0];
        yield 'future is clamped' => [100, 0];
    }

    #[DataProvider('provideLoadBoundaryExpectations')]
    public function testSourceEditedAfterClassLoadingBeforeResourceConstruction(bool $requestStartAvailable, bool $stale)
    {
        $directory = sys_get_temp_dir().'/sf-reflection-capture-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $class = 'CapturedClass'.bin2hex(random_bytes(6));
        $path = $directory.'/class.php';
        $metadata = $directory.'/resource';
        $server = $_SERVER;
        unset($_SERVER['SOURCE_DATE_EPOCH']);
        if ($requestStartAvailable) {
            $_SERVER['REQUEST_TIME'] = time() - 10;
        } else {
            unset($_SERVER['REQUEST_TIME']);
        }
        file_put_contents($path, '<?php class '.$class.' { public function __construct(public string $value = "v1") {} }');
        touch($path, time() - 100);
        require $path;
        file_put_contents($path, str_replace('v1', 'v2', file_get_contents($path)));
        touch($path, time() - 1);
        clearstatcache();
        $resource = new ReflectionClassResource(new \ReflectionClass($class));
        file_put_contents($metadata, serialize($resource));

        foreach (ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if ($loader->findFile(ReflectionClassResource::class)) {
                $autoload = $vendor.'/autoload.php';
                break;
            }
        }
        try {
            $process = proc_open([\PHP_BINARY, '-r', 'require $argv[1]; require $argv[2]; echo unserialize(file_get_contents($argv[3]))->isFresh(time() + 10) ? "fresh" : "stale";', $autoload, $path, $metadata], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $error);
            self::assertSame($stale ? 'stale' : 'fresh', $output);
        } finally {
            $_SERVER = $server;
            unlink($path);
            unlink($metadata);
            rmdir($directory);
        }
    }

    public static function provideLoadBoundaryExpectations(): iterable
    {
        yield 'ordinary request detects the edit' => [true, true];
        yield 'missing request start retains the pre-capture limit' => [false, false];
    }

    public function testLongRunningRequestRechecksCallbacksButMissingBoundaryUsesConstructionTime()
    {
        $directory = sys_get_temp_dir().'/sf-reflection-callbacks-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $class = 'ReflectionCallbackCost'.bin2hex(random_bytes(6));
        $counter = 'reflection_callback_'.bin2hex(random_bytes(6));
        $path = $directory.'/class.php';
        file_put_contents($path, '<?php class '.$class.' implements Symfony\Contracts\Service\ServiceSubscriberInterface { public static function getSubscribedServices(): array { ++$GLOBALS['.var_export($counter, true).']; return []; } }');
        touch($path, time() - 10);
        require $path;
        $server = $_SERVER;
        unset($_SERVER['SOURCE_DATE_EPOCH']);
        $GLOBALS[$counter] = 0;
        $_SERVER['REQUEST_TIME'] = time() - 100;
        try {
            $resource = new ReflectionClassResource(new \ReflectionClass($class));
            self::assertSame(0, $GLOBALS[$counter]);
            serialize($resource);
            self::assertSame(1, $GLOBALS[$counter]);
            self::assertTrue($resource->isFresh(time() + 10));
            self::assertTrue($resource->isFresh(time() + 10));
            self::assertSame(3, $GLOBALS[$counter]);

            unset($_SERVER['REQUEST_TIME']);
            $resource = new ReflectionClassResource(new \ReflectionClass($class));
            serialize($resource);
            self::assertSame(4, $GLOBALS[$counter]);
            self::assertTrue($resource->isFresh(time() + 10));
            self::assertTrue($resource->isFresh(time() + 10));
            self::assertSame(4, $GLOBALS[$counter]);
        } finally {
            $_SERVER = $server;
            unset($GLOBALS[$counter]);
            unlink($path);
            rmdir($directory);
        }
    }

    public function testLegacyMetadataRemainsReadable()
    {
        $data = (new ReflectionClassResource(new \ReflectionClass(self::class)))->__serialize();
        unset($data['loadedAt']);
        $resource = LegacyResource::create(ReflectionClassResource::class, $data);
        self::assertTrue($resource->isFresh(time() + 10));
    }

    public function testSerializedFormIgnoresRequestTimeWithSourceDateEpoch()
    {
        $server = $_SERVER;
        $_SERVER['SOURCE_DATE_EPOCH'] = '1700000000';
        try {
            $_SERVER['REQUEST_TIME'] = time();
            $before = serialize(new ReflectionClassResource(new \ReflectionClass(self::class)));
            $_SERVER['REQUEST_TIME'] -= 100000;
            self::assertSame($before, serialize(new ReflectionClassResource(new \ReflectionClass(self::class))));
        } finally {
            $_SERVER = $server;
        }
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

enum SomeEnum
{
    case Alpha;

    #[TestAttribute]
    case Beta;
}

enum SomeBackedEnum: string
{
    case Alpha = 'alpha';

    #[TestAttribute]
    case Beta = 'beta';
}
