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

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;
use Symfony\Component\VarExporter\Internal\Registry;
use Symfony\Component\VarExporter\VarExporter;

class VarExporterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testPhpIncompleteClassesAreForbidden()
    {
        $this->expectException('Symfony\Component\VarExporter\Exception\ClassNotFoundException');
        $this->expectExceptionMessage('Class "SomeNotExistingClass" not found.');
        $unserializeCallback = ini_set('unserialize_callback_func', 'var_dump');
        try {
            Registry::unserialize([], ['O:20:"SomeNotExistingClass":0:{}']);
        } finally {
            $this->assertSame('var_dump', ini_set('unserialize_callback_func', $unserializeCallback));
        }
    }

    /**
     * @dataProvider provideFailingSerialization
     */
    public function testFailingSerialization($value)
    {
        $this->expectException('Symfony\Component\VarExporter\Exception\NotInstantiableTypeException');
        $this->expectExceptionMessageRegExp('/Type ".*" is not instantiable\./');
        $expectedDump = $this->getDump($value);
        try {
            VarExporter::export($value);
        } finally {
            $this->assertDumpEquals(rtrim($expectedDump), $value);
        }
    }

    public function provideFailingSerialization()
    {
        yield [hash_init('md5')];
        yield [new \ReflectionClass('stdClass')];
        yield [(new \ReflectionFunction(function (): int {}))->getReturnType()];
        yield [new \ReflectionGenerator((function () { yield 123; })())];
        yield [function () {}];
        yield [function () { yield 123; }];
        yield [new \SplFileInfo(__FILE__)];
        yield [$h = fopen(__FILE__, 'r')];
        yield [[$h]];

        $a = new class() {
        };

        yield [$a];

        $a = [null, $h];
        $a[0] = &$a;

        yield [$a];
    }

    /**
     * @dataProvider provideExport
     */
    public function testExport(string $testName, $value, bool $staticValueExpected = false)
    {
        $dumpedValue = $this->getDump($value);
        $isStaticValue = true;
        $marshalledValue = VarExporter::export($value, $isStaticValue);

        $this->assertSame($staticValueExpected, $isStaticValue);
        if ('var-on-sleep' !== $testName && 'php74-serializable' !== $testName) {
            $this->assertDumpEquals($dumpedValue, $value);
        }

        $dump = "<?php\n\nreturn ".$marshalledValue.";\n";
        $dump = str_replace(var_export(__FILE__, true), "\\dirname(__DIR__).\\DIRECTORY_SEPARATOR.'VarExporterTest.php'", $dump);

        if (\PHP_VERSION_ID < 70400 && \in_array($testName, ['array-object', 'array-iterator', 'array-object-custom', 'spl-object-storage', 'final-array-iterator', 'final-error'], true)) {
            $fixtureFile = __DIR__.'/Fixtures/'.$testName.'-legacy.php';
        } else {
            $fixtureFile = __DIR__.'/Fixtures/'.$testName.'.php';
        }
        $this->assertStringEqualsFile($fixtureFile, $dump);

        if ('incomplete-class' === $testName || 'external-references' === $testName) {
            return;
        }
        $marshalledValue = include $fixtureFile;

        if (!$isStaticValue) {
            if ($value instanceof MyWakeup) {
                $value->bis = null;
            }
            $this->assertDumpEquals($value, $marshalledValue);
        } else {
            $this->assertSame($value, $marshalledValue);
        }
    }

    public function provideExport()
    {
        yield ['multiline-string', ["\0\0\r\nA" => "B\rC\n\n"], true];

        yield ['bool', true, true];
        yield ['simple-array', [123, ['abc']], true];
        yield ['partially-indexed-array', [5 => true, 1 => true, 2 => true, 6 => true], true];
        yield ['datetime', \DateTime::createFromFormat('U', 0)];

        $value = new \ArrayObject();
        $value[0] = 1;
        $value->foo = new \ArrayObject();
        $value[1] = $value;

        yield ['array-object', $value];

        yield ['array-iterator', new \ArrayIterator([123], 1)];
        yield ['array-object-custom', new MyArrayObject([234])];

        $value = new MySerializable();

        yield ['serializable', [$value, $value]];

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

        $rt = new \ReflectionProperty('Error', 'trace');
        $rt->setAccessible(true);
        $rt->setValue($value, ['file' => __FILE__, 'line' => 123]);

        $rl = new \ReflectionProperty('Error', 'line');
        $rl->setAccessible(true);
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

        yield ['foo-serializable', new FooSerializable('bar')];

        yield ['private-constructor', PrivateConstructor::create('bar')];

        yield ['php74-serializable', new Php74Serializable()];
    }
}

class MySerializable implements \Serializable
{
    public function serialize()
    {
        return '123';
    }

    public function unserialize($data)
    {
        // no-op
    }
}

class MyWakeup
{
    public $sub;
    public $bis;
    public $baz;
    public $def = 234;

    public function __sleep()
    {
        return ['sub', 'baz'];
    }

    public function __wakeup()
    {
        if (123 === $this->sub) {
            $this->bis = 123;
            $this->baz = 123;
        }
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

    public function setFlags($flags)
    {
        throw new \BadMethodCallException('Calling MyArrayObject::setFlags() is forbidden');
    }
}

class GoodNight
{
    public function __sleep()
    {
        $this->good = 'night';

        return ['good'];
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
    public function serialize()
    {
        return serialize([123, parent::serialize()]);
    }

    public function unserialize($data)
    {
        if ('' === $data) {
            throw new \InvalidArgumentException('Serialized data is empty.');
        }
        list(, $data) = unserialize($data);
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

class FooSerializable implements \Serializable
{
    private $foo;

    public function __construct(string $foo)
    {
        $this->foo = $foo;
    }

    public function getFoo(): string
    {
        return $this->foo;
    }

    public function serialize(): string
    {
        return serialize([$this->getFoo()]);
    }

    public function unserialize($str)
    {
        list($this->foo) = unserialize($str);
    }
}

class Php74Serializable implements \Serializable
{
    public function __serialize()
    {
        return [$this->foo = new \stdClass()];
    }

    public function __unserialize(array $data)
    {
        list($this->foo) = $data;
    }

    public function __sleep()
    {
        throw new \BadMethodCallException();
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException();
    }

    public function serialize()
    {
        throw new \BadMethodCallException();
    }

    public function unserialize($ser)
    {
        throw new \BadMethodCallException();
    }
}
