<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;
use Symfony\Component\VarExporter\Exception\ClassNotFoundException;
use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;
use Symfony\Component\VarExporter\Internal\Registry;
use Symfony\Component\VarExporter\Tests\Fixtures\BackedProperty;
use Symfony\Component\VarExporter\Tests\Fixtures\FooReadonly;
use Symfony\Component\VarExporter\Tests\Fixtures\FooSerializable;
use Symfony\Component\VarExporter\Tests\Fixtures\FooUnitEnum;
use Symfony\Component\VarExporter\Tests\Fixtures\GoodNight;
use Symfony\Component\VarExporter\Tests\Fixtures\MySerializable;
use Symfony\Component\VarExporter\Tests\Fixtures\MyWakeup;
use Symfony\Component\VarExporter\Tests\Fixtures\Php74Serializable;
use Symfony\Component\VarExporter\Tests\Fixtures\PrivateFCC;
use Symfony\Component\VarExporter\VarExporter;

$errorHandler = set_error_handler(static function (int $errno, string $errstr) use (&$errorHandler) {
    if (\E_DEPRECATED === $errno && str_contains($errstr, 'serialize()')) {
        // We're testing if the component handles deprecated Serializable and __sleep/wakeup implementations well.
        // This kind of implementation triggers a deprecation warning that we explicitly want to ignore here.
        return true;
    }

    return $errorHandler ? $errorHandler(...\func_get_args()) : false;
});

try {
    foreach ([
        MySerializable::class,
        FooSerializable::class,
        GoodNight::class,
        Php74Serializable::class,
        MyWakeup::class,
    ] as $class) {
        class_exists($class);
    }
} finally {
    restore_error_handler();
}

class VarExporterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testPhpIncompleteClassesAreForbidden()
    {
        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage('Class "SomeNotExistingClass" not found.');
        $unserializeCallback = ini_set('unserialize_callback_func', 'var_dump');
        try {
            Registry::unserialize([], ['O:20:"SomeNotExistingClass":0:{}']);
        } finally {
            $this->assertSame('var_dump', ini_set('unserialize_callback_func', $unserializeCallback));
        }
    }

    #[DataProvider('provideFailingSerialization')]
    public function testFailingSerialization($value)
    {
        $this->expectException(NotInstantiableTypeException::class);
        $this->expectExceptionMessageMatches('/Type ".*" is not instantiable\./');
        $expectedDump = $this->getDump($value);
        try {
            VarExporter::export($value);
        } finally {
            $this->assertDumpEquals(rtrim($expectedDump), $value);
        }
    }

    public static function provideFailingSerialization()
    {
        yield [hash_init('md5')];
        yield [new \ReflectionClass(\stdClass::class)];
        yield [(new \ReflectionFunction(function (): int {}))->getReturnType()];
        yield [new \ReflectionGenerator((function () { yield 123; })())];
        yield [function () {}];
        yield [function () { yield 123; }];
        yield [new \SplFileInfo(__FILE__)];
        yield [$h = fopen(__FILE__, 'r')];
        yield [[$h]];

        $a = new class {
        };

        yield [$a];

        $a = [null, $h];
        $a[0] = &$a;

        yield [$a];
    }

    #[DataProvider('provideExport')]
    public function testExport(string $testName, $value, bool $staticValueExpected = false)
    {
        $dumpedValue = $this->getDump($value);
        $isStaticValue = null;
        $marshalledValue = VarExporter::export($value, $isStaticValue);

        $this->assertSame($staticValueExpected, $isStaticValue);
        if ('var-on-sleep' !== $testName && 'php74-serializable' !== $testName) {
            $this->assertDumpEquals($dumpedValue, $value);
        }

        $dump = "<?php\n\nreturn ".$marshalledValue.";\n";
        $dump = str_replace(var_export(__FILE__, true), "\\dirname(__DIR__).\\DIRECTORY_SEPARATOR.'VarExporterTest.php'", $dump);

        $fixtureFile = __DIR__.'/Fixtures/'.$testName.'.php';
        $this->assertStringEqualsFile($fixtureFile, $dump);

        if ('incomplete-class' === $testName || 'external-references' === $testName) {
            return;
        }
        $marshalledValue = include $fixtureFile;

        if (!$isStaticValue || 'named-closure-static' === $testName || 'private-fcc' === $testName) {
            if ($value instanceof MyWakeup) {
                $value->bis = null;
            }
            $this->assertDumpEquals($value, $marshalledValue);
        } else {
            $this->assertSame($value, $marshalledValue);
        }
    }

    public static function provideExport()
    {
        yield ['multiline-string', ["\0\0\r\nA" => "B\rC\n\n"], true];
        yield ['lf-ending-string', "'BOOM'\n.var_dump(123)//'", true];

        yield ['bool', true, true];
        yield ['simple-array', [123, ['abc']], true];
        yield ['partially-indexed-array', [5 => true, 1 => true, 2 => true, 6 => true], true];
        yield ['datetime', [
            \DateTime::createFromFormat('U', 0),
            \DateTimeImmutable::createFromFormat('U', 0),
            $tz = new \DateTimeZone('Europe/Paris'),
            $interval = ($start = new \DateTimeImmutable('2009-10-11', $tz))->diff(new \DateTimeImmutable('2009-10-18', $tz)),
            new \DatePeriod($start, $interval, 4),
        ]];

        $value = new ArrayObject();
        $value[0] = 1;
        $value->foo = new \ArrayObject();
        $value[1] = $value;

        yield ['array-object', $value];

        yield ['array-iterator', new \ArrayIterator([123], 1)];
        yield ['array-object-custom', new MyArrayObject([234])];

        $mySerializable = new MySerializable();
        $fooSerializable = new FooSerializable('bar');

        yield ['serializable', [$mySerializable, $mySerializable]];
        yield ['foo-serializable', $fooSerializable];

        unset($mySerializable, $fooSerializable, $errorHandler);

        $value = new MyWakeup();
        $value->sub = new MyWakeup();
        $value->sub->sub = 123;
        $value->sub->bis = 123;
        $value->sub->baz = 123;

        yield ['wakeup', $value];

        yield ['clone', [new MyCloneable(), new MyNotCloneable()]];

        yield ['private', [new MyPrivateValue(123, 234), new MyPrivateChildValue(123, 234)]];

        $value = new \SplObjectStorage();
        $value[new \stdClass()] = 345;

        yield ['spl-object-storage', $value];

        yield ['incomplete-class', unserialize('O:20:"SomeNotExistingClass":0:{}')];

        $value = [(object) []];
        $value[1] = &$value[0];
        $value[2] = $value[0];

        yield ['hard-references', $value];

        $value = [];
        $value[0] = &$value;

        yield ['hard-references-recursive', $value];

        static $value = [123];

        yield ['external-references', [&$value], true];

        unset($value);

        $value = new \Error();

        $rt = new \ReflectionProperty(\Error::class, 'trace');
        $rt->setValue($value, ['file' => __FILE__, 'line' => 123]);

        $rl = new \ReflectionProperty(\Error::class, 'line');
        $rl->setValue($value, 234);

        yield ['error', $value];

        yield ['var-on-sleep', new GoodNight()];

        $value = new FinalError(false);
        $rt->setValue($value, []);
        $rl->setValue($value, 123);

        yield ['final-error', $value];

        yield ['final-array-iterator', new FinalArrayIterator()];

        yield ['final-stdclass', new FinalStdClass()];

        $value = new MyWakeup();
        $value->bis = new \ReflectionClass($value);

        yield ['wakeup-refl', $value];

        yield ['abstract-parent', new ConcreteClass()];

        yield ['private-constructor', PrivateConstructor::create('bar')];

        yield ['php74-serializable', new Php74Serializable()];

        yield ['__unserialize-but-no-__serialize', new __UnserializeButNo__Serialize()];

        yield ['__serialize-but-no-__unserialize', new __SerializeButNo__Unserialize()];

        yield ['unit-enum', [FooUnitEnum::Bar], true];
        yield ['readonly', new FooReadonly('k', 'v')];

        yield ['named-closure-method', (new TestClass())->testMethod(...)];
        yield ['named-closure-static', TestClass::testStaticMethod(...), true];

        if (\PHP_VERSION_ID < 80400) {
            return;
        }

        yield ['backed-property', new BackedProperty('name')];

        if (\PHP_VERSION_ID < 80500) {
            return;
        }

        yield ['private-fcc', (new \ReflectionClass(PrivateFCC::class))->getAttributes(PrivateFCC::class)[0]->getArguments()[0], true];
    }

    public function testUnicodeDirectionality()
    {
        $this->assertSame('"\0\r\u{202A}\u{202B}\u{202D}\u{202E}\u{2066}\u{2067}\u{2068}\u{202C}\u{2069}\n"', VarExporter::export("\0\r\u{202A}\u{202B}\u{202D}\u{202E}\u{2066}\u{2067}\u{2068}\u{202C}\u{2069}\n"));
    }
}

class MyCloneable
{
    public function __clone()
    {
        throw new \Exception('__clone should never be called');
    }
}

class MyNotCloneable
{
    private function __clone()
    {
        throw new \Exception('__clone should never be called');
    }
}

class PrivateConstructor
{
    public $prop;

    public static function create($prop): self
    {
        return new self($prop);
    }

    private function __construct($prop)
    {
        $this->prop = $prop;
    }
}

class TestClass
{
    public function testMethod()
    {
        return 'test';
    }

    public static function testStaticMethod()
    {
        return 'test';
    }
}

class MyPrivateValue
{
    protected $prot;
    private $priv;

    public function __construct($prot, $priv)
    {
        $this->prot = $prot;
        $this->priv = $priv;
    }
}

class MyPrivateChildValue extends MyPrivateValue
{
}

class MyArrayObject extends \ArrayObject
{
    private $unused = 123;

    public function __construct(array $array)
    {
        parent::__construct($array, 1);
    }

    public function setFlags($flags): void
    {
        throw new \BadMethodCallException('Calling MyArrayObject::setFlags() is forbidden');
    }
}

final class FinalError extends \Error
{
    public function __construct(bool $throw = true)
    {
        if ($throw) {
            throw new \BadMethodCallException('Should not be called.');
        }
    }
}

final class FinalArrayIterator extends \ArrayIterator
{
    public function serialize(): string
    {
        return serialize([123, parent::serialize()]);
    }

    public function unserialize($data): void
    {
        if ('' === $data) {
            throw new \InvalidArgumentException('Serialized data is empty.');
        }
        [, $data] = unserialize($data);
        parent::unserialize($data);
    }
}

final class FinalStdClass extends \stdClass
{
    public function __clone()
    {
        throw new \BadMethodCallException('Should not be called.');
    }
}

abstract class AbstractClass
{
    protected $foo;
    private $bar;

    protected function setBar($bar)
    {
        $this->bar = $bar;
    }
}

class ConcreteClass extends AbstractClass
{
    public function __construct()
    {
        $this->foo = 123;
        $this->setBar(234);
    }
}

#[\AllowDynamicProperties]
class ArrayObject extends \ArrayObject
{
}

class __UnserializeButNo__Serialize
{
    public $foo;

    public function __construct()
    {
        $this->foo = 'ccc';
    }

    public function __unserialize(array $data): void
    {
        $this->foo = $data['foo'];
    }
}

class ParentOf__SerializeButNo__Unserialize
{
    private $foo = 'foo';

    public function getFoo()
    {
        return $this->foo;
    }
}

class __SerializeButNo__Unserialize extends ParentOf__SerializeButNo__Unserialize
{
    public $baz;
    private $bar;

    public function __construct()
    {
        $this->baz = 'ccc';
        $this->bar = 'ddd';
    }

    public function __serialize(): array
    {
        return [
            'foo' => $this->getFoo(),
            'baz' => $this->baz,
            'bar' => $this->bar,
        ];
    }
}
