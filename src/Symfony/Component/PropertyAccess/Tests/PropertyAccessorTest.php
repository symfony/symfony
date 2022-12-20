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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\Tests\Fixtures\ExtendedUninitializedProperty;
use Symfony\Component\PropertyAccess\Tests\Fixtures\ReturnTyped;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestAdderRemoverInvalidArgumentLength;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestAdderRemoverInvalidMethods;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassIsWritable;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassMagicCall;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassMagicGet;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassSetValue;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassTypedProperty;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassTypeErrorInsideCall;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestPublicPropertyDynamicallyCreated;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestPublicPropertyGetterOnObject;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestPublicPropertyGetterOnObjectMagicGet;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestSingularAndPluralProps;
use Symfony\Component\PropertyAccess\Tests\Fixtures\Ticket5775Object;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TypeHinted;
use Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedPrivateProperty;
use Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedProperty;

class PropertyAccessorTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @var PropertyAccessor
     */
    private $propertyAccessor;

    protected function setUp(): void
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    public function getPathsWithUnexpectedType()
    {
        return [
            ['', 'foobar'],
            ['foo', 'foobar'],
            [null, 'foobar'],
            [123, 'foobar'],
            [(object) ['prop' => null], 'prop.foobar'],
            [(object) ['prop' => (object) ['subProp' => null]], 'prop.subProp.foobar'],
            [['index' => null], '[index][foobar]'],
            [['index' => ['subIndex' => null]], '[index][subIndex][foobar]'],
        ];
    }

    public function getPathsWithMissingProperty()
    {
        return [
            [(object) ['firstName' => 'Bernhard'], 'lastName'],
            [(object) ['property' => (object) ['firstName' => 'Bernhard']], 'property.lastName'],
            [['index' => (object) ['firstName' => 'Bernhard']], '[index].lastName'],
            [new TestClass('Bernhard'), 'protectedProperty'],
            [new TestClass('Bernhard'), 'privateProperty'],
            [new TestClass('Bernhard'), 'protectedAccessor'],
            [new TestClass('Bernhard'), 'protectedIsAccessor'],
            [new TestClass('Bernhard'), 'protectedHasAccessor'],
            [new TestClass('Bernhard'), 'privateAccessor'],
            [new TestClass('Bernhard'), 'privateIsAccessor'],
            [new TestClass('Bernhard'), 'privateHasAccessor'],

            // Properties are not camelized
            [new TestClass('Bernhard'), 'public_property'],
        ];
    }

    public function getPathsWithMissingIndex()
    {
        return [
            [['firstName' => 'Bernhard'], '[lastName]'],
            [[], '[index][lastName]'],
            [['index' => []], '[index][lastName]'],
            [['index' => ['firstName' => 'Bernhard']], '[index][lastName]'],
            [(object) ['property' => ['firstName' => 'Bernhard']], 'property[lastName]'],
        ];
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testGetValue($objectOrArray, $path, $value)
    {
        self::assertSame($value, $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testGetValueThrowsExceptionIfPropertyNotFound($objectOrArray, $path)
    {
        self::expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testGetValueReturnsNullIfPropertyNotFoundAndExceptionIsDisabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_SET, PropertyAccessor::DO_NOT_THROW);

        self::assertNull($this->propertyAccessor->getValue($objectOrArray, $path), $path);
    }

    /**
     * @group legacy
     * @dataProvider getPathsWithMissingProperty
     */
    public function testGetValueReturnsNullIfPropertyNotFoundAndExceptionIsDisabledUsingBooleanArgument($objectOrArray, $path)
    {
        $this->expectDeprecation('Since symfony/property-access 5.3: Passing a boolean as the fourth argument to "Symfony\Component\PropertyAccess\PropertyAccessor::__construct()" is deprecated. Pass a combination of bitwise flags as the second argument instead (i.e an integer).');

        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_SET, PropertyAccessor::DO_NOT_THROW, null, false);

        self::assertNull($this->propertyAccessor->getValue($objectOrArray, $path), $path);
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testGetValueThrowsNoExceptionIfIndexNotFound($objectOrArray, $path)
    {
        self::assertNull($this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testGetValueThrowsExceptionIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        self::expectException(NoSuchIndexException::class);
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH);
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @group legacy
     * @dataProvider getPathsWithMissingIndex
     */
    public function testGetValueThrowsExceptionIfIndexNotFoundAndIndexExceptionsEnabledUsingBooleanArgument($objectOrArray, $path)
    {
        self::expectException(NoSuchIndexException::class);
        $this->expectDeprecation('Since symfony/property-access 5.3: Passing a boolean as the second argument to "Symfony\Component\PropertyAccess\PropertyAccessor::__construct()" is deprecated. Pass a combination of bitwise flags instead (i.e an integer).');

        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, true);
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @requires PHP 7.4
     */
    public function testGetValueThrowsExceptionIfUninitializedProperty()
    {
        self::expectException(UninitializedPropertyException::class);
        self::expectExceptionMessage('The property "Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedProperty::$uninitialized" is not readable because it is typed "string". You should initialize it or declare a default value instead.');

        $this->propertyAccessor->getValue(new UninitializedProperty(), 'uninitialized');
    }

    public function testGetValueThrowsExceptionIfUninitializedPropertyWithGetter()
    {
        self::expectException(UninitializedPropertyException::class);
        self::expectExceptionMessage('The method "Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedPrivateProperty::getUninitialized()" returned "null", but expected type "array". Did you forget to initialize a property or to make the return type nullable using "?array"?');

        $this->propertyAccessor->getValue(new UninitializedPrivateProperty(), 'uninitialized');
    }

    public function testGetValueThrowsExceptionIfUninitializedPropertyWithGetterOfAnonymousClass()
    {
        self::expectException(UninitializedPropertyException::class);
        self::expectExceptionMessage('The method "class@anonymous::getUninitialized()" returned "null", but expected type "array". Did you forget to initialize a property or to make the return type nullable using "?array"?');

        $object = eval('return new class() {
            private $uninitialized;

            public function getUninitialized(): array
            {
                return $this->uninitialized;
            }
        };');

        $this->propertyAccessor->getValue($object, 'uninitialized');
    }

    /**
     * @requires PHP 7.4
     */
    public function testGetValueThrowsExceptionIfUninitializedNotNullablePropertyWithGetterOfAnonymousClass()
    {
        self::expectException(UninitializedPropertyException::class);
        self::expectExceptionMessage('The property "class@anonymous::$uninitialized" is not readable because it is typed "string". You should initialize it or declare a default value instead.');

        $object = eval('return new class() {
            private string $uninitialized;

            public function getUninitialized(): string
            {
                return $this->uninitialized;
            }
        };');

        $this->propertyAccessor->getValue($object, 'uninitialized');
    }

    /**
     * @requires PHP 7.4
     */
    public function testGetValueThrowsExceptionIfUninitializedPropertyOfAnonymousClass()
    {
        self::expectException(UninitializedPropertyException::class);
        self::expectExceptionMessage('The property "class@anonymous::$uninitialized" is not readable because it is typed "string". You should initialize it or declare a default value instead.');

        $object = eval('return new class() {
            public string $uninitialized;
        };');

        $this->propertyAccessor->getValue($object, 'uninitialized');
    }

    /**
     * @requires PHP 7.4
     */
    public function testGetValueThrowsExceptionIfUninitializedNotNullableOfParentClass()
    {
        self::expectException(UninitializedPropertyException::class);
        self::expectExceptionMessage('The property "Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedProperty::$uninitialized" is not readable because it is typed "string". You should initialize it or declare a default value instead.');

        $this->propertyAccessor->getValue(new ExtendedUninitializedProperty(), 'uninitialized');
    }

    /**
     * @requires PHP 7.4
     */
    public function testGetValueThrowsExceptionIfUninitializedNotNullablePropertyWithGetterOfParentClass()
    {
        self::expectException(UninitializedPropertyException::class);
        self::expectExceptionMessage('The property "Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedProperty::$privateUninitialized" is not readable because it is typed "string". You should initialize it or declare a default value instead.');

        $this->propertyAccessor->getValue(new ExtendedUninitializedProperty(), 'privateUninitialized');
    }

    public function testGetValueThrowsExceptionIfUninitializedPropertyWithGetterOfAnonymousStdClass()
    {
        self::expectException(UninitializedPropertyException::class);
        self::expectExceptionMessage('The method "stdClass@anonymous::getUninitialized()" returned "null", but expected type "array". Did you forget to initialize a property or to make the return type nullable using "?array"?');

        $object = eval('return new class() extends \stdClass {
            private $uninitialized;

            public function getUninitialized(): array
            {
                return $this->uninitialized;
            }
        };');

        $this->propertyAccessor->getValue($object, 'uninitialized');
    }

    public function testGetValueThrowsExceptionIfUninitializedPropertyWithGetterOfAnonymousChildClass()
    {
        self::expectException(UninitializedPropertyException::class);
        self::expectExceptionMessage('The method "Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedPrivateProperty@anonymous::getUninitialized()" returned "null", but expected type "array". Did you forget to initialize a property or to make the return type nullable using "?array"?');

        $object = eval('return new class() extends \Symfony\Component\PropertyAccess\Tests\Fixtures\UninitializedPrivateProperty {};');

        $this->propertyAccessor->getValue($object, 'uninitialized');
    }

    public function testGetValueThrowsExceptionIfNotArrayAccess()
    {
        self::expectException(NoSuchIndexException::class);
        $this->propertyAccessor->getValue(new \stdClass(), '[index]');
    }

    public function testGetValueReadsMagicGet()
    {
        self::assertSame('Bernhard', $this->propertyAccessor->getValue(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    public function testGetValueIgnoresMagicGet()
    {
        self::expectException(NoSuchPropertyException::class);

        $propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS);

        $propertyAccessor->getValue(new TestClassMagicGet('Bernhard'), 'magicProperty');
    }

    public function testGetValueReadsArrayWithMissingIndexForCustomPropertyPath()
    {
        $object = new \ArrayObject();
        $array = ['child' => ['index' => $object]];

        self::assertNull($this->propertyAccessor->getValue($array, '[child][index][foo][bar]'));
        self::assertSame([], $object->getArrayCopy());
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicGetThatReturnsConstant()
    {
        self::assertSame('constant value', $this->propertyAccessor->getValue(new TestClassMagicGet('Bernhard'), 'constantMagicProperty'));
    }

    public function testGetValueNotModifyObject()
    {
        $object = new \stdClass();
        $object->firstName = ['Bernhard'];

        self::assertNull($this->propertyAccessor->getValue($object, 'firstName[1]'));
        self::assertSame(['Bernhard'], $object->firstName);
    }

    public function testGetValueNotModifyObjectException()
    {
        $propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH);
        $object = new \stdClass();
        $object->firstName = ['Bernhard'];

        try {
            $propertyAccessor->getValue($object, 'firstName[1]');
        } catch (NoSuchIndexException $e) {
        }

        self::assertSame(['Bernhard'], $object->firstName);
    }

    public function testGetValueDoesNotReadMagicCallByDefault()
    {
        self::expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'magicCallProperty');
    }

    /**
     * @group legacy
     * @expectedDeprecation Since symfony/property-access 5.2: Passing a boolean as the first argument to "Symfony\Component\PropertyAccess\PropertyAccessor::__construct()" is deprecated. Pass a combination of bitwise flags instead (i.e an integer).
     */
    public function testLegacyGetValueReadsMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        self::assertSame('Bernhard', $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    public function testGetValueReadsMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_SET | PropertyAccessor::MAGIC_CALL);

        self::assertSame('Bernhard', $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicCallThatReturnsConstant()
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::MAGIC_CALL);

        self::assertSame('constant value', $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'constantMagicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testGetValueThrowsExceptionIfNotObjectOrArray($objectOrArray, $path)
    {
        self::expectException(UnexpectedTypeException::class);
        self::expectExceptionMessage('PropertyAccessor requires a graph of objects or arrays to operate on');
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testSetValue($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        self::assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testSetValueThrowsExceptionIfPropertyNotFound($objectOrArray, $path)
    {
        self::expectException(NoSuchPropertyException::class);
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFound($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        self::assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH);
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        self::assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    public function testSetValueThrowsExceptionIfNotArrayAccess()
    {
        self::expectException(NoSuchIndexException::class);
        $object = new \stdClass();

        $this->propertyAccessor->setValue($object, '[index]', 'Updated');
    }

    public function testSetValueUpdatesMagicSet()
    {
        $author = new TestClassMagicGet('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicProperty', 'Updated');

        self::assertEquals('Updated', $author->__get('magicProperty'));
    }

    public function testSetValueIgnoresMagicSet()
    {
        self::expectException(NoSuchPropertyException::class);
        $propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS);

        $author = new TestClassMagicGet('Bernhard');

        $propertyAccessor->setValue($author, 'magicProperty', 'Updated');
    }

    public function testSetValueThrowsExceptionIfThereAreMissingParameters()
    {
        self::expectException(NoSuchPropertyException::class);
        $object = new TestClass('Bernhard');

        $this->propertyAccessor->setValue($object, 'publicAccessorWithMoreRequiredParameters', 'Updated');
    }

    public function testSetValueDoesNotUpdateMagicCallByDefault()
    {
        self::expectException(NoSuchPropertyException::class);
        $author = new TestClassMagicCall('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');
    }

    /**
     * @group legacy
     * @expectedDeprecation Since symfony/property-access 5.2: Passing a boolean as the first argument to "Symfony\Component\PropertyAccess\PropertyAccessor::__construct()" is deprecated. Pass a combination of bitwise flags instead (i.e an integer).
     */
    public function testLegacySetValueUpdatesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $author = new TestClassMagicCall('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');

        self::assertEquals('Updated', $author->__call('getMagicCallProperty', []));
    }

    public function testSetValueUpdatesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::MAGIC_CALL);

        $author = new TestClassMagicCall('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');

        self::assertEquals('Updated', $author->__call('getMagicCallProperty', []));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testSetValueThrowsExceptionIfNotObjectOrArray($objectOrArray, $path)
    {
        self::expectException(UnexpectedTypeException::class);
        self::expectExceptionMessage('PropertyAccessor requires a graph of objects or arrays to operate on');
        $this->propertyAccessor->setValue($objectOrArray, $path, 'value');
    }

    public function testGetValueWhenArrayValueIsNull()
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH);
        self::assertNull($this->propertyAccessor->getValue(['index' => ['nullable' => null]], '[index][nullable]'));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsReadable($objectOrArray, $path)
    {
        self::assertTrue($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testIsReadableReturnsFalseIfPropertyNotFound($objectOrArray, $path)
    {
        self::assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsReadableReturnsTrueIfIndexNotFound($objectOrArray, $path)
    {
        // Non-existing indices can be read. In this case, null is returned
        self::assertTrue($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsReadableReturnsFalseIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH);

        // When exceptions are enabled, non-existing indices cannot be read
        self::assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    public function testIsReadableRecognizesMagicGet()
    {
        self::assertTrue($this->propertyAccessor->isReadable(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    public function testIsReadableDoesNotRecognizeMagicCallByDefault()
    {
        self::assertFalse($this->propertyAccessor->isReadable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    /**
     * @group legacy
     * @expectedDeprecation Since symfony/property-access 5.2: Passing a boolean as the first argument to "Symfony\Component\PropertyAccess\PropertyAccessor::__construct()" is deprecated. Pass a combination of bitwise flags instead (i.e an integer).
     */
    public function testLegacyIsReadableRecognizesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        self::assertTrue($this->propertyAccessor->isReadable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    public function testIsReadableRecognizesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::MAGIC_CALL);

        self::assertTrue($this->propertyAccessor->isReadable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testIsReadableReturnsFalseIfNotObjectOrArray($objectOrArray, $path)
    {
        self::assertFalse($this->propertyAccessor->isReadable($objectOrArray, $path));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsWritable($objectOrArray, $path)
    {
        self::assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     */
    public function testIsWritableReturnsFalseIfPropertyNotFound($objectOrArray, $path)
    {
        self::assertFalse($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsWritableReturnsTrueIfIndexNotFound($objectOrArray, $path)
    {
        // Non-existing indices can be written. Arrays are created on-demand.
        self::assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testIsWritableReturnsTrueIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH);

        // Non-existing indices can be written even if exceptions are enabled
        self::assertTrue($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    public function testIsWritableRecognizesMagicSet()
    {
        self::assertTrue($this->propertyAccessor->isWritable(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    public function testIsWritableDoesNotRecognizeMagicCallByDefault()
    {
        self::assertFalse($this->propertyAccessor->isWritable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    /**
     * @group legacy
     * @expectedDeprecation Since symfony/property-access 5.2: Passing a boolean as the first argument to "Symfony\Component\PropertyAccess\PropertyAccessor::__construct()" is deprecated. Pass a combination of bitwise flags instead (i.e an integer).
     */
    public function testLegacyIsWritableRecognizesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        self::assertTrue($this->propertyAccessor->isWritable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    public function testIsWritableRecognizesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(PropertyAccessor::MAGIC_CALL);

        self::assertTrue($this->propertyAccessor->isWritable(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     */
    public function testIsWritableReturnsFalseIfNotObjectOrArray($objectOrArray, $path)
    {
        self::assertFalse($this->propertyAccessor->isWritable($objectOrArray, $path));
    }

    public function getValidPropertyPaths()
    {
        return [
            [['Bernhard', 'Schussek'], '[0]', 'Bernhard'],
            [['Bernhard', 'Schussek'], '[1]', 'Schussek'],
            [['firstName' => 'Bernhard'], '[firstName]', 'Bernhard'],
            [['index' => ['firstName' => 'Bernhard']], '[index][firstName]', 'Bernhard'],
            [(object) ['firstName' => 'Bernhard'], 'firstName', 'Bernhard'],
            [(object) ['property' => ['firstName' => 'Bernhard']], 'property[firstName]', 'Bernhard'],
            [['index' => (object) ['firstName' => 'Bernhard']], '[index].firstName', 'Bernhard'],
            [(object) ['property' => (object) ['firstName' => 'Bernhard']], 'property.firstName', 'Bernhard'],

            // Accessor methods
            [new TestClass('Bernhard'), 'publicProperty', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicAccessor', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicAccessorWithDefaultValue', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicAccessorWithRequiredAndDefaultValue', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicIsAccessor', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicHasAccessor', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicGetSetter', 'Bernhard'],
            [new TestClass('Bernhard'), 'publicCanAccessor', 'Bernhard'],

            // Methods are camelized
            [new TestClass('Bernhard'), 'public_accessor', 'Bernhard'],
            [new TestClass('Bernhard'), '_public_accessor', 'Bernhard'],

            // Missing indices
            [['index' => []], '[index][firstName]', null],
            [['root' => ['index' => []]], '[root][index][firstName]', null],

            // Special chars
            [['%!@$§.' => 'Bernhard'], '[%!@$§.]', 'Bernhard'],
            [['index' => ['%!@$§.' => 'Bernhard']], '[index][%!@$§.]', 'Bernhard'],
            [(object) ['%!@$§' => 'Bernhard'], '%!@$§', 'Bernhard'],
            [(object) ['property' => (object) ['%!@$§' => 'Bernhard']], 'property.%!@$§', 'Bernhard'],

            // nested objects and arrays
            [['foo' => new TestClass('bar')], '[foo].publicGetSetter', 'bar'],
            [new TestClass(['foo' => 'bar']), 'publicGetSetter[foo]', 'bar'],
            [new TestClass(new TestClass('bar')), 'publicGetter.publicGetSetter', 'bar'],
            [new TestClass(['foo' => new TestClass('bar')]), 'publicGetter[foo].publicGetSetter', 'bar'],
            [new TestClass(new TestClass(new TestClass('bar'))), 'publicGetter.publicGetter.publicGetSetter', 'bar'],
            [new TestClass(['foo' => ['baz' => new TestClass('bar')]]), 'publicGetter[foo][baz].publicGetSetter', 'bar'],
        ];
    }

    public function testTicket5755()
    {
        $object = new Ticket5775Object();

        $this->propertyAccessor->setValue($object, 'property', 'foobar');

        self::assertEquals('foobar', $object->getProperty());
    }

    public function testSetValueDeepWithMagicGetter()
    {
        $obj = new TestClassMagicGet('foo');
        $obj->publicProperty = ['foo' => ['bar' => 'some_value']];
        $this->propertyAccessor->setValue($obj, 'publicProperty[foo][bar]', 'Updated');
        self::assertSame('Updated', $obj->publicProperty['foo']['bar']);
    }

    public function getReferenceChainObjectsForSetValue()
    {
        return [
            [['a' => ['b' => ['c' => 'old-value']]], '[a][b][c]', 'new-value'],
            [new TestClassSetValue(new TestClassSetValue('old-value')), 'value.value', 'new-value'],
            [new TestClassSetValue(['a' => ['b' => ['c' => new TestClassSetValue('old-value')]]]), 'value[a][b][c].value', 'new-value'],
            [new TestClassSetValue(['a' => ['b' => 'old-value']]), 'value[a][b]', 'new-value'],
            [new \ArrayIterator(['a' => ['b' => ['c' => 'old-value']]]), '[a][b][c]', 'new-value'],
        ];
    }

    /**
     * @dataProvider getReferenceChainObjectsForSetValue
     */
    public function testSetValueForReferenceChainIssue($object, $path, $value)
    {
        $this->propertyAccessor->setValue($object, $path, $value);

        self::assertEquals($value, $this->propertyAccessor->getValue($object, $path));
    }

    public function getReferenceChainObjectsForIsWritable()
    {
        return [
            [new TestClassIsWritable(['a' => ['b' => 'old-value']]), 'value[a][b]', false],
            [new TestClassIsWritable(new \ArrayIterator(['a' => ['b' => 'old-value']])), 'value[a][b]', true],
            [new TestClassIsWritable(['a' => ['b' => ['c' => new TestClassSetValue('old-value')]]]), 'value[a][b][c].value', true],
        ];
    }

    /**
     * @dataProvider getReferenceChainObjectsForIsWritable
     */
    public function testIsWritableForReferenceChainIssue($object, $path, $value)
    {
        self::assertEquals($value, $this->propertyAccessor->isWritable($object, $path));
    }

    public function testThrowTypeError()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Expected argument of type "DateTime", "string" given at property path "date"');
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', 'This is a string, \DateTime expected.');
    }

    public function testThrowTypeErrorWithNullArgument()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Expected argument of type "DateTime", "null" given');
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', null);
    }

    public function testSetTypeHint()
    {
        $date = new \DateTime();
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', $date);
        self::assertSame($date, $object->getDate());
    }

    public function testArrayNotBeeingOverwritten()
    {
        $value = ['value1' => 'foo', 'value2' => 'bar'];
        $object = new TestClass($value);

        $this->propertyAccessor->setValue($object, 'publicAccessor[value2]', 'baz');
        self::assertSame('baz', $this->propertyAccessor->getValue($object, 'publicAccessor[value2]'));
        self::assertSame(['value1' => 'foo', 'value2' => 'baz'], $object->getPublicAccessor());
    }

    public function testCacheReadAccess()
    {
        $obj = new TestClass('foo');

        $propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, new ArrayAdapter());
        self::assertEquals('foo', $propertyAccessor->getValue($obj, 'publicGetSetter'));
        $propertyAccessor->setValue($obj, 'publicGetSetter', 'bar');
        $propertyAccessor->setValue($obj, 'publicGetSetter', 'baz');
        self::assertEquals('baz', $propertyAccessor->getValue($obj, 'publicGetSetter'));
    }

    public function testAttributeWithSpecialChars()
    {
        $obj = new \stdClass();
        $obj->{'@foo'} = 'bar';
        $obj->{'a/b'} = '1';
        $obj->{'a%2Fb'} = '2';

        $propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, new ArrayAdapter());
        self::assertSame('bar', $propertyAccessor->getValue($obj, '@foo'));
        self::assertSame('1', $propertyAccessor->getValue($obj, 'a/b'));
        self::assertSame('2', $propertyAccessor->getValue($obj, 'a%2Fb'));
    }

    public function testThrowTypeErrorWithInterface()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Expected argument of type "Countable", "string" given');
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'countable', 'This is a string, \Countable expected.');
    }

    public function testAnonymousClassRead()
    {
        $value = 'bar';

        $obj = $this->generateAnonymousClass($value);

        $propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, new ArrayAdapter());

        self::assertEquals($value, $propertyAccessor->getValue($obj, 'foo'));
    }

    public function testAnonymousClassReadThrowExceptionOnInvalidPropertyPath()
    {
        self::expectException(NoSuchPropertyException::class);
        $obj = $this->generateAnonymousClass('bar');

        $this->propertyAccessor->getValue($obj, 'invalid_property');
    }

    public function testAnonymousClassReadReturnsNullOnInvalidPropertyWithDisabledException()
    {
        $obj = $this->generateAnonymousClass('bar');

        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor();

        self::assertNull($this->propertyAccessor->getValue($obj, 'invalid_property'));
    }

    public function testAnonymousClassWrite()
    {
        $value = 'bar';

        $obj = $this->generateAnonymousClass('');

        $propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS, PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, new ArrayAdapter());
        $propertyAccessor->setValue($obj, 'foo', $value);

        self::assertEquals($value, $propertyAccessor->getValue($obj, 'foo'));
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

    public function testThrowTypeErrorInsideSetterCall()
    {
        self::expectException(\TypeError::class);
        $object = new TestClassTypeErrorInsideCall();

        $this->propertyAccessor->setValue($object, 'property', 'foo');
    }

    public function testDoNotDiscardReturnTypeError()
    {
        self::expectException(\TypeError::class);
        $object = new ReturnTyped();

        $this->propertyAccessor->setValue($object, 'foos', [new \DateTime()]);
    }

    public function testDoNotDiscardReturnTypeErrorWhenWriterMethodIsMisconfigured()
    {
        self::expectException(\TypeError::class);
        $object = new ReturnTyped();

        $this->propertyAccessor->setValue($object, 'name', 'foo');
    }

    public function testWriteToSingularPropertyWhilePluralOneExists()
    {
        $object = new TestSingularAndPluralProps();

        $this->propertyAccessor->isWritable($object, 'email'); // cache access info
        $this->propertyAccessor->setValue($object, 'email', 'test@email.com');

        self::assertEquals('test@email.com', $object->getEmail());
        self::assertEmpty($object->getEmails());
    }

    public function testWriteToPluralPropertyWhileSingularOneExists()
    {
        $object = new TestSingularAndPluralProps();

        $this->propertyAccessor->isWritable($object, 'emails'); // cache access info
        $this->propertyAccessor->setValue($object, 'emails', ['test@email.com']);

        self::assertEquals(['test@email.com'], $object->getEmails());
        self::assertNull($object->getEmail());
    }

    public function testAdderAndRemoverArePreferredOverSetter()
    {
        $object = new TestPluralAdderRemoverAndSetter();

        $this->propertyAccessor->isWritable($object, 'emails'); // cache access info
        $this->propertyAccessor->setValue($object, 'emails', ['test@email.com']);

        self::assertEquals(['test@email.com'], $object->getEmails());
    }

    public function testAdderAndRemoverArePreferredOverSetterForSameSingularAndPlural()
    {
        $object = new TestPluralAdderRemoverAndSetterSameSingularAndPlural();

        $this->propertyAccessor->isWritable($object, 'aircraft'); // cache access info
        $this->propertyAccessor->setValue($object, 'aircraft', ['aeroplane']);

        self::assertEquals(['aeroplane'], $object->getAircraft());
    }

    public function testAdderWithoutRemover()
    {
        self::expectException(NoSuchPropertyException::class);
        self::expectExceptionMessageMatches('/.*The add method "addFoo" in class "Symfony\\\Component\\\PropertyAccess\\\Tests\\\Fixtures\\\TestAdderRemoverInvalidMethods" was found, but the corresponding remove method "removeFoo" was not found\./');
        $object = new TestAdderRemoverInvalidMethods();
        $this->propertyAccessor->setValue($object, 'foos', [1, 2]);
    }

    public function testRemoverWithoutAdder()
    {
        self::expectException(NoSuchPropertyException::class);
        self::expectExceptionMessageMatches('/.*The remove method "removeBar" in class "Symfony\\\Component\\\PropertyAccess\\\Tests\\\Fixtures\\\TestAdderRemoverInvalidMethods" was found, but the corresponding add method "addBar" was not found\./');
        $object = new TestAdderRemoverInvalidMethods();
        $this->propertyAccessor->setValue($object, 'bars', [1, 2]);
    }

    public function testAdderAndRemoveNeedsTheExactParametersDefined()
    {
        self::expectException(NoSuchPropertyException::class);
        self::expectExceptionMessageMatches('/.*The method "addFoo" in class "Symfony\\\Component\\\PropertyAccess\\\Tests\\\Fixtures\\\TestAdderRemoverInvalidArgumentLength" requires 0 arguments, but should accept only 1\./');
        $object = new TestAdderRemoverInvalidArgumentLength();
        $this->propertyAccessor->setValue($object, 'foo', [1, 2]);
    }

    public function testSetterNeedsTheExactParametersDefined()
    {
        self::expectException(NoSuchPropertyException::class);
        self::expectExceptionMessageMatches('/.*The method "setBar" in class "Symfony\\\Component\\\PropertyAccess\\\Tests\\\Fixtures\\\TestAdderRemoverInvalidArgumentLength" requires 2 arguments, but should accept only 1\./');
        $object = new TestAdderRemoverInvalidArgumentLength();
        $this->propertyAccessor->setValue($object, 'bar', [1, 2]);
    }

    public function testSetterNeedsPublicAccess()
    {
        self::expectException(NoSuchPropertyException::class);
        self::expectExceptionMessageMatches('/.*The method "setFoo" in class "Symfony\\\Component\\\PropertyAccess\\\Tests\\\Fixtures\\\TestClassSetValue" was found but does not have public access./');
        $object = new TestClassSetValue(0);
        $this->propertyAccessor->setValue($object, 'foo', 1);
    }

    public function testGetPublicProperty()
    {
        $value = 'A';
        $path = 'a';
        $object = new TestPublicPropertyGetterOnObject();

        self::assertSame($value, $this->propertyAccessor->getValue($object, $path));
    }

    public function testGetPrivateProperty()
    {
        $object = new TestPublicPropertyGetterOnObject();

        self::expectException(NoSuchPropertyException::class);
        self::expectExceptionMessageMatches('/.*Can\'t get a way to read the property "b" in class "Symfony\\\Component\\\PropertyAccess\\\Tests\\\Fixtures\\\TestPublicPropertyGetterOnObject./');
        $this->propertyAccessor->getValue($object, 'b');
    }

    public function testGetDynamicPublicProperty()
    {
        $value = 'Bar';
        $path = 'foo';
        $object = new TestPublicPropertyDynamicallyCreated('Bar');

        self::assertSame($value, $this->propertyAccessor->getValue($object, $path));
    }

    public function testGetDynamicPublicPropertyWithMagicGetterDisallow()
    {
        $object = new TestPublicPropertyGetterOnObjectMagicGet();
        $propertyAccessor = new PropertyAccessor(PropertyAccessor::DISALLOW_MAGIC_METHODS);

        self::expectException(NoSuchPropertyException::class);
        $propertyAccessor->getValue($object, 'c');
    }

    public function testGetDynamicPublicPropertyWithMagicGetterAllow()
    {
        $value = 'B';
        $path = 'b';
        $object = new TestPublicPropertyGetterOnObjectMagicGet();
        self::assertSame($value, $this->propertyAccessor->getValue($object, $path));
    }

    /**
     * @requires PHP 7.4
     */
    public function testSetValueWrongTypeShouldThrowWrappedException()
    {
        $object = new TestClassTypedProperty();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Expected argument of type "float", "string" given at property path "publicProperty"');
        $this->propertyAccessor->setValue($object, 'publicProperty', 'string');
    }
}
