<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Marshaller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Marshaller\PhpMarshaller;
use Symfony\Component\Cache\Marshaller\PhpMarshaller\Registry;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class DoctrineProviderTest extends TestCase
{
    use VarDumperTestTrait;

    /**
     * @expectedException \ReflectionException
     * @expectedExceptionMessage Class SomeNotExistingClass does not exist
     */
    public function testPhpIncompleteClassesAreForbidden()
    {
        $unserializeCallback = ini_set('unserialize_callback_func', 'var_dump');
        try {
            Registry::__set_state(array('O:20:"SomeNotExistingClass":0:{}'));
        } finally {
            $this->assertSame('var_dump', ini_set('unserialize_callback_func', $unserializeCallback));
        }
    }

    /**
     * @dataProvider provideFailingSerialization
     * @expectedException \Exception
     * @expectedExceptionMessageRegexp Serialization of '.*' is not allowed
     */
    public function testFailingSerialization($value)
    {
        $expectedDump = $this->getDump($value);
        try {
            PhpMarshaller::marshall($value);
        } finally {
            $this->assertDumpEquals(rtrim($expectedDump), $value);
        }
    }

    public function provideFailingSerialization()
    {
        yield array(hash_init('md5'));
        yield array(new \ReflectionClass('stdClass'));
        yield array((new \ReflectionFunction(function (): int {}))->getReturnType());
        yield array(new \ReflectionGenerator((function () { yield 123; })()));
        yield array(function () {});
        yield array(function () { yield 123; });
        yield array(new \SplFileInfo(__FILE__));
        yield array($h = fopen(__FILE__, 'r'));
        yield array(array($h));

        $a = array(null, $h);
        $a[0] = &$a;

        yield array($a);
    }

    /**
     * @dataProvider provideMarshall
     */
    public function testMarshall(string $testName, $value, bool $staticValueExpected = false)
    {
        $serializedValue = serialize($value);
        $isStaticValue = true;
        $marshalledValue = PhpMarshaller::marshall($value, $isStaticValue);

        $this->assertSame($staticValueExpected, $isStaticValue);
        $this->assertSame($serializedValue, serialize($value));

        $dump = '<?php return '.$marshalledValue.";\n";
        $fixtureFile = __DIR__.'/Fixtures/'.$testName.'.php';
        $this->assertStringEqualsFile($fixtureFile, $dump);

        if ('incomplete-class' === $testName) {
            return;
        }
        $marshalledValue = include $fixtureFile;

        if (!$isStaticValue) {
            $this->assertDumpEquals($value, $marshalledValue);
        } else {
            $this->assertSame($value, $marshalledValue);
        }
    }

    public function provideMarshall()
    {
        yield array('bool', true, true);
        yield array('simple-array', array(123, array('abc')), true);
        yield array('datetime', \DateTime::createFromFormat('U', 0));

        $value = new \ArrayObject();
        $value[0] = 1;
        $value->foo = new \ArrayObject();
        $value[1] = $value;

        yield array('array-object', $value);

        yield array('array-iterator', new \ArrayIterator(array(123), 1));
        yield array('array-object-custom', new MyArrayObject(array(234)));

        $value = new MySerializable();

        yield array('serializable', array($value, $value));

        $value = new MyWakeup();
        $value->sub = new MyWakeup();
        $value->sub->sub = 123;
        $value->sub->bis = 123;
        $value->sub->baz = 123;

        yield array('wakeup', $value);

        yield array('clone', array(new MyCloneable(), new MyNotCloneable()));

        yield array('private', array(new MyPrivateValue(123, 234), new MyPrivateChildValue(123, 234)));

        $value = new \SplObjectStorage();
        $value[new \stdClass()] = 345;

        yield array('spl-object-storage', $value);

        yield array('incomplete-class', unserialize('O:20:"SomeNotExistingClass":0:{}'));

        $value = array((object) array());
        $value[1] = &$value[0];
        $value[2] = $value[0];

        yield array('hard-references', $value);

        $value = array();
        $value[0] = &$value;

        yield array('hard-references-recursive', $value);
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
        return array('sub', 'baz');
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
