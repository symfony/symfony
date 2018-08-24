<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\Tests\Fixtures\ReturnTyped;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassIsWritable;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassMagicCall;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassMagicGet;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassSetValue;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassTypeErrorInsideCall;
use Symfony\Component\PropertyAccess\Tests\Fixtures\Ticket5775Object;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TypeHinted;

class PropertyAccessorTest extends TestCase
{
    /**
     * @var PropertyAccessor
     */
    private $propertyAccessor;

    protected function setUp()
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    public function getPathsWithUnexpectedType()
    {
        return array(
            array('', 'foobar'),
            array('foo', 'foobar'),
            array(null, 'foobar'),
            array(123, 'foobar'),
            array((object) array('prop' => null), 'prop.foobar'),
            array((object) array('prop' => (object) array('subProp' => null)), 'prop.subProp.foobar'),
            array(array('index' => null), '[index][foobar]'),
            array(array('index' => array('subIndex' => null)), '[index][subIndex][foobar]'),
        );
    }

    public function getPathsWithMissingProperty()
    {
        return array(
            array((object) array('firstName' => 'Bernhard'), 'lastName'),
            array((object) array('property' => (object) array('firstName' => 'Bernhard')), 'property.lastName'),
            array(array('index' => (object) array('firstName' => 'Bernhard')), '[index].lastName'),
            array(new TestClass('Bernhard'), 'protectedProperty'),
            array(new TestClass('Bernhard'), 'privateProperty'),
            array(new TestClass('Bernhard'), 'protectedAccessor'),
            array(new TestClass('Bernhard'), 'protectedIsAccessor'),
            array(new TestClass('Bernhard'), 'protectedHasAccessor'),
            array(new TestClass('Bernhard'), 'privateAccessor'),
            array(new TestClass('Bernhard'), 'privateIsAccessor'),
            array(new TestClass('Bernhard'), 'privateHasAccessor'),

            // Properties are not camelized
            array(new TestClass('Bernhard'), 'public_property'),
        );
    }

    public function getPathsWithMissingIndex()
    {
        return array(
            array(array('firstName' => 'Bernhard'), '[lastName]'),
            array(array(), '[index][lastName]'),
            array(array('index' => array()), '[index][lastName]'),
            array(array('index' => array('firstName' => 'Bernhard')), '[index][lastName]'),
            array((object) array('property' => array('firstName' => 'Bernhard')), 'property[lastName]'),
        );
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testGetValue($objectOrArray, $path, $value)
    {
        $this->assertSame($value, $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfPropertyNotFound($objectOrArray, $path)
    {
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testGetValueThrowsNoExceptionIfIndexNotFound($objectOrArray, $path)
    {
        $this->assertNull($this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchIndexException
     */
    public function testGetValueThrowsExceptionIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchIndexException
     */
    public function testGetValueThrowsExceptionIfNotArrayAccess()
    {
        $this->propertyAccessor->getValue(new \stdClass(), '[index]');
    }

    public function testGetValueReadsMagicGet()
    {
        $this->assertSame('Bernhard', $this->propertyAccessor->getValue(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    public function testGetValueReadsArrayWithMissingIndexForCustomPropertyPath()
    {
        $object = new \ArrayObject();
        $array = array('child' => array('index' => $object));

        $this->assertNull($this->propertyAccessor->getValue($array, '[child][index][foo][bar]'));
        $this->assertSame(array(), $object->getArrayCopy());
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicGetThatReturnsConstant()
    {
        $this->assertSame('constant value', $this->propertyAccessor->getValue(new TestClassMagicGet('Bernhard'), 'constantMagicProperty'));
    }

    public function testGetValueNotModifyObject()
    {
        $object = new \stdClass();
        $object->firstName = array('Bernhard');

        $this->assertNull($this->propertyAccessor->getValue($object, 'firstName[1]'));
        $this->assertSame(array('Bernhard'), $object->firstName);
    }

    public function testGetValueNotModifyObjectException()
    {
        $propertyAccessor = new PropertyAccessor(false, true);
        $object = new \stdClass();
        $object->firstName = array('Bernhard');

        try {
            $propertyAccessor->getValue($object, 'firstName[1]');
        } catch (NoSuchIndexException $e) {
        }

        $this->assertSame(array('Bernhard'), $object->firstName);
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueDoesNotReadMagicCallByDefault()
    {
        $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'magicCallProperty');
    }

    public function testGetValueReadsMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertSame('Bernhard', $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicCallThatReturnsConstant()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertSame('constant value', $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'constantMagicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     * @expectedExceptionMessage PropertyAccessor requires a graph of objects or arrays to operate on
     */
    public function testGetValueThrowsExceptionIfNotObjectOrArray($objectOrArray, $path)
    {
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testSetValue($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueThrowsExceptionIfPropertyNotFound($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFound($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchIndexException
     */
    public function testSetValueThrowsExceptionIfNotArrayAccess()
    {
        $object = new \stdClass();

        $this->propertyAccessor->setValue($object, '[index]', 'Updated');
    }

    public function testSetValueUpdatesMagicSet()
    {
        $author = new TestClassMagicGet('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicProperty', 'Updated');

        $this->assertEquals('Updated', $author->__get('magicProperty'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueThrowsExceptionIfThereAreMissingParameters()
    {
        $object = new TestClass('Bernhard');

        $this->propertyAccessor->setValue($object, 'publicAccessorWithMoreRequiredParameters', 'Updated');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueDoesNotUpdateMagicCallByDefault()
    {
        $author = new TestClassMagicCall('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');
    }

    public function testSetValueUpdatesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $author = new TestClassMagicCall('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');

        $this->assertEquals('Updated', $author->__call('getMagicCallProperty', array()));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     * @expectedExceptionMessage PropertyAccessor requires a graph of objects or arrays to operate on
     */
    public function testSetValueThrowsExceptionIfNotObjectOrArray($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'value');
    }

    public function testGetValueWhenArrayValueIsNull()
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);
        $this->assertNull($this->propertyAccessor->getValue(array('index' => array('nullable' => null)), '[index][nullable]'));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsReadable($objectOrArray, $path)
    {
        $this->assertTrue($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testIsReadableReturnsFalseIfPropertyNotFound($objectOrArray, $path)
    {
        $this->assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsReadableReturnsTrueIfIndexNotFound($objectOrArray, $path)
    {
        // Non-existing indices can be read. In this case, null is returned
        $this->assertTrue($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsReadableReturnsFalseIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);

        // When exceptions are enabled, non-existing indices cannot be read
        $this->assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    public function testIsReadableRecognizesMagicGet()
    {
        $this->assertTrue($this->propertyAccessor->isReadable(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    public function testIsReadableDoesNotRecognizeMagicCallByDefault()
    {
        $this->assertFalse($this->propertyAccessor->isReadable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    public function testIsReadableRecognizesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertTrue($this->propertyAccessor->isReadable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testIsReadableReturnsFalseIfNotObjectOrArray($objectOrArray, $path)
    {
        $this->assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsWritable($objectOrArray, $path)
    {
        $this->assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testIsWritableReturnsFalseIfPropertyNotFound($objectOrArray, $path)
    {
        $this->assertFalse($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsWritableReturnsTrueIfIndexNotFound($objectOrArray, $path)
    {
        // Non-existing indices can be written. Arrays are created on-demand.
        $this->assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsWritableReturnsTrueIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);

        // Non-existing indices can be written even if exceptions are enabled
        $this->assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    public function testIsWritableRecognizesMagicSet()
    {
        $this->assertTrue($this->propertyAccessor->isWritable(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    public function testIsWritableDoesNotRecognizeMagicCallByDefault()
    {
        $this->assertFalse($this->propertyAccessor->isWritable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    public function testIsWritableRecognizesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertTrue($this->propertyAccessor->isWritable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testIsWritableReturnsFalseIfNotObjectOrArray($objectOrArray, $path)
    {
        $this->assertFalse($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    public function getValidPropertyPaths()
    {
        return array(
            array(array('Bernhard', 'Schussek'), '[0]', 'Bernhard'),
            array(array('Bernhard', 'Schussek'), '[1]', 'Schussek'),
            array(array('firstName' => 'Bernhard'), '[firstName]', 'Bernhard'),
            array(array('index' => array('firstName' => 'Bernhard')), '[index][firstName]', 'Bernhard'),
            array((object) array('firstName' => 'Bernhard'), 'firstName', 'Bernhard'),
            array((object) array('property' => array('firstName' => 'Bernhard')), 'property[firstName]', 'Bernhard'),
            array(array('index' => (object) array('firstName' => 'Bernhard')), '[index].firstName', 'Bernhard'),
            array((object) array('property' => (object) array('firstName' => 'Bernhard')), 'property.firstName', 'Bernhard'),

            // Accessor methods
            array(new TestClass('Bernhard'), 'publicProperty', 'Bernhard'),
            array(new TestClass('Bernhard'), 'publicAccessor', 'Bernhard'),
            array(new TestClass('Bernhard'), 'publicAccessorWithDefaultValue', 'Bernhard'),
            array(new TestClass('Bernhard'), 'publicAccessorWithRequiredAndDefaultValue', 'Bernhard'),
            array(new TestClass('Bernhard'), 'publicIsAccessor', 'Bernhard'),
            array(new TestClass('Bernhard'), 'publicHasAccessor', 'Bernhard'),
            array(new TestClass('Bernhard'), 'publicGetSetter', 'Bernhard'),

            // Methods are camelized
            array(new TestClass('Bernhard'), 'public_accessor', 'Bernhard'),
            array(new TestClass('Bernhard'), '_public_accessor', 'Bernhard'),

            // Missing indices
            array(array('index' => array()), '[index][firstName]', null),
            array(array('root' => array('index' => array())), '[root][index][firstName]', null),

            // Special chars
            array(array('%!@$§.' => 'Bernhard'), '[%!@$§.]', 'Bernhard'),
            array(array('index' => array('%!@$§.' => 'Bernhard')), '[index][%!@$§.]', 'Bernhard'),
            array((object) array('%!@$§' => 'Bernhard'), '%!@$§', 'Bernhard'),
            array((object) array('property' => (object) array('%!@$§' => 'Bernhard')), 'property.%!@$§', 'Bernhard'),

            // nested objects and arrays
            array(array('foo' => new TestClass('bar')), '[foo].publicGetSetter', 'bar'),
            array(new TestClass(array('foo' => 'bar')), 'publicGetSetter[foo]', 'bar'),
            array(new TestClass(new TestClass('bar')), 'publicGetter.publicGetSetter', 'bar'),
            array(new TestClass(array('foo' => new TestClass('bar'))), 'publicGetter[foo].publicGetSetter', 'bar'),
            array(new TestClass(new TestClass(new TestClass('bar'))), 'publicGetter.publicGetter.publicGetSetter', 'bar'),
            array(new TestClass(array('foo' => array('baz' => new TestClass('bar')))), 'publicGetter[foo][baz].publicGetSetter', 'bar'),
        );
    }

    public function testTicket5755()
    {
        $object = new Ticket5775Object();

        $this->propertyAccessor->setValue($object, 'property', 'foobar');

        $this->assertEquals('foobar', $object->getProperty());
    }

    public function testSetValueDeepWithMagicGetter()
    {
        $obj = new TestClassMagicGet('foo');
        $obj->publicProperty = array('foo' => array('bar' => 'some_value'));
        $this->propertyAccessor->setValue($obj, 'publicProperty[foo][bar]', 'Updated');
        $this->assertSame('Updated', $obj->publicProperty['foo']['bar']);
    }

    public function getReferenceChainObjectsForSetValue()
    {
        return array(
            array(array('a' => array('b' => array('c' => 'old-value'))), '[a][b][c]', 'new-value'),
            array(new TestClassSetValue(new TestClassSetValue('old-value')), 'value.value', 'new-value'),
            array(new TestClassSetValue(array('a' => array('b' => array('c' => new TestClassSetValue('old-value'))))), 'value[a][b][c].value', 'new-value'),
            array(new TestClassSetValue(array('a' => array('b' => 'old-value'))), 'value[a][b]', 'new-value'),
            array(new \ArrayIterator(array('a' => array('b' => array('c' => 'old-value')))), '[a][b][c]', 'new-value'),
        );
    }

    /**
     * @dataProvider getReferenceChainObjectsForSetValue
     */
    public function testSetValueForReferenceChainIssue($object, $path, $value)
    {
        $this->propertyAccessor->setValue($object, $path, $value);

        $this->assertEquals($value, $this->propertyAccessor->getValue($object, $path));
    }

    public function getReferenceChainObjectsForIsWritable()
    {
        return array(
            array(new TestClassIsWritable(array('a' => array('b' => 'old-value'))), 'value[a][b]', false),
            array(new TestClassIsWritable(new \ArrayIterator(array('a' => array('b' => 'old-value')))), 'value[a][b]', true),
            array(new TestClassIsWritable(array('a' => array('b' => array('c' => new TestClassSetValue('old-value'))))), 'value[a][b][c].value', true),
        );
    }

    /**
     * @dataProvider getReferenceChainObjectsForIsWritable
     */
    public function testIsWritableForReferenceChainIssue($object, $path, $value)
    {
        $this->assertEquals($value, $this->propertyAccessor->isWritable($object, $path));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @expectedExceptionMessage Expected argument of type "DateTime", "string" given
     */
    public function testThrowTypeError()
    {
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', 'This is a string, \DateTime expected.');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @expectedExceptionMessage Expected argument of type "DateTime", "NULL" given
     */
    public function testThrowTypeErrorWithNullArgument()
    {
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', null);
    }

    public function testSetTypeHint()
    {
        $date = new \DateTime();
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', $date);
        $this->assertSame($date, $object->getDate());
    }

    public function testArrayNotBeeingOverwritten()
    {
        $value = array('value1' => 'foo', 'value2' => 'bar');
        $object = new TestClass($value);

        $this->propertyAccessor->setValue($object, 'publicAccessor[value2]', 'baz');
        $this->assertSame('baz', $this->propertyAccessor->getValue($object, 'publicAccessor[value2]'));
        $this->assertSame(array('value1' => 'foo', 'value2' => 'baz'), $object->getPublicAccessor());
    }

    public function testCacheReadAccess()
    {
        $obj = new TestClass('foo');

        $propertyAccessor = new PropertyAccessor(false, false, new ArrayAdapter());
        $this->assertEquals('foo', $propertyAccessor->getValue($obj, 'publicGetSetter'));
        $propertyAccessor->setValue($obj, 'publicGetSetter', 'bar');
        $propertyAccessor->setValue($obj, 'publicGetSetter', 'baz');
        $this->assertEquals('baz', $propertyAccessor->getValue($obj, 'publicGetSetter'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @expectedExceptionMessage Expected argument of type "Countable", "string" given
     */
    public function testThrowTypeErrorWithInterface()
    {
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'countable', 'This is a string, \Countable expected.');
    }

    /**
     * @requires PHP 7.0
     */
    public function testAnonymousClassRead()
    {
        $value = 'bar';

        $obj = $this->generateAnonymousClass($value);

        $propertyAccessor = new PropertyAccessor(false, false, new ArrayAdapter());

        $this->assertEquals($value, $propertyAccessor->getValue($obj, 'foo'));
    }

    /**
     * @requires PHP 7.0
     */
    public function testAnonymousClassWrite()
    {
        $value = 'bar';

        $obj = $this->generateAnonymousClass('');

        $propertyAccessor = new PropertyAccessor(false, false, new ArrayAdapter());
        $propertyAccessor->setValue($obj, 'foo', $value);

        $this->assertEquals($value, $propertyAccessor->getValue($obj, 'foo'));
    }

    private function generateAnonymousClass($value)
    {
        $obj = eval('return new class($value)
        {
            private $foo;

            public function __construct($foo)
            {
                $this->foo = $foo;
            }

            /**
             * @return mixed
             */
            public function getFoo()
            {
                return $this->foo;
            }

            /**
             * @param mixed $foo
             */
            public function setFoo($foo)
            {
                $this->foo = $foo;
            }
        };');

        return $obj;
    }

    /**
     * @requires PHP 7.0
     * @expectedException \TypeError
     */
    public function testThrowTypeErrorInsideSetterCall()
    {
        $object = new TestClassTypeErrorInsideCall();

        $this->propertyAccessor->setValue($object, 'property', 'foo');
    }

    /**
     * @requires PHP 7
     *
     * @expectedException \TypeError
     */
    public function testDoNotDiscardReturnTypeError()
    {
        $object = new ReturnTyped();

        $this->propertyAccessor->setValue($object, 'foos', array(new \DateTime()));
    }

    /**
     * @requires PHP 7
     *
     * @expectedException \TypeError
     */
    public function testDoNotDiscardReturnTypeErrorWhenWriterMethodIsMisconfigured()
    {
        $object = new ReturnTyped();

        $this->propertyAccessor->setValue($object, 'name', 'foo');
    }
}
