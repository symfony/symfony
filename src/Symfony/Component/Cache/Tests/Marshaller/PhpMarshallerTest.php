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
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class DoctrineProviderTest extends TestCase
{
    use VarDumperTestTrait;

    /**
     * @dataProvider provideMarshall
     */
    public function testMarshall(string $testName, $value, int $expectedObjectsCount)
    {
        $objectsCount = 0;
        $marshalledValue = PhpMarshaller::marshall($value, $objectsCount);

        $this->assertSame($expectedObjectsCount, $objectsCount);

        $dump = '<?php return '.var_export($marshalledValue, true).";\n";
        $fixtureFile = __DIR__.'/Fixtures/'.$testName.'.php';
        $this->assertStringEqualsFile($fixtureFile, $dump);

        if ($objectsCount) {
            $marshalledValue = include $fixtureFile;
            $this->assertDumpEquals($value, $marshalledValue);

            $dump = PhpMarshaller::optimize($dump);
            $fixtureFile = __DIR__.'/Fixtures/'.$testName.'.optimized.php';
            $this->assertStringEqualsFile($fixtureFile, $dump);

            $marshalledValue = include $fixtureFile;
            $this->assertDumpEquals($value, $marshalledValue);
        } else {
            $this->assertSame($value, $marshalledValue);
        }
    }

    public function provideMarshall()
    {
        yield array('bool', true, 0);
        yield array('simple-array', array(123, array('abc')), 0);
        yield array('datetime', \DateTime::createFromFormat('U', 0), 1);

        $value = new \ArrayObject();
        $value[0] = 1;
        $value->foo = new \ArrayObject();
        $value[1] = $value;

        yield array('array-object', $value, 3);

        yield array('array-iterator', new \ArrayIterator(array(123), 1), 1);
        yield array('array-object-custom', new MyArrayObject(array(234)), 1);

        $value = new MySerializable();

        yield array('serializable', array($value, $value), 2);

        $value = new MyWakeup();
        $value->sub = new MyWakeup();
        $value->sub->sub = 123;
        $value->sub->bis = 123;
        $value->sub->baz = 123;

        yield array('wakeup', $value, 2);

        yield array('clone', array(new MyCloneable(), new MyNotCloneable()), 2);

        yield array('private', array(new MyPrivateValue(123, 234), new MyPrivateChildValue(123, 234)), 2);

        $value = new \SplObjectStorage();
        $value[new \stdClass()] = 345;

        yield array('spl-object-storage', $value, 2);
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
