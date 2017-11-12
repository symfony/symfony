<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver\Tests;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OptionsResolverTest extends TestCase
{
    /**
     * @var OptionsResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OptionsResolver();
    }

    ////////////////////////////////////////////////////////////////////////////
    // resolve()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist. Defined options are: "a", "z".
     */
    public function testResolveFailsIfNonExistingOption(): void
    {
        $this->resolver->setDefault('z', '1');
        $this->resolver->setDefault('a', '2');

        $this->resolver->resolve(array('foo' => 'bar'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The options "baz", "foo", "ping" do not exist. Defined options are: "a", "z".
     */
    public function testResolveFailsIfMultipleNonExistingOptions(): void
    {
        $this->resolver->setDefault('z', '1');
        $this->resolver->setDefault('a', '2');

        $this->resolver->resolve(array('ping' => 'pong', 'foo' => 'bar', 'baz' => 'bam'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testResolveFailsFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->resolve(array());
        });

        $this->resolver->resolve();
    }

    ////////////////////////////////////////////////////////////////////////////
    // setDefault()/hasDefault()
    ////////////////////////////////////////////////////////////////////////////

    public function testSetDefaultReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->setDefault('foo', 'bar'));
    }

    public function testSetDefault(): void
    {
        $this->resolver->setDefault('one', '1');
        $this->resolver->setDefault('two', '20');

        $this->assertEquals(array(
            'one' => '1',
            'two' => '20',
        ), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetDefaultFromLazyOption(): void
    {
        $this->resolver->setDefault('lazy', function (Options $options): void {
            $options->setDefault('default', 42);
        });

        $this->resolver->resolve();
    }

    public function testHasDefault(): void
    {
        $this->assertFalse($this->resolver->hasDefault('foo'));
        $this->resolver->setDefault('foo', 42);
        $this->assertTrue($this->resolver->hasDefault('foo'));
    }

    public function testHasDefaultWithNullValue(): void
    {
        $this->assertFalse($this->resolver->hasDefault('foo'));
        $this->resolver->setDefault('foo', null);
        $this->assertTrue($this->resolver->hasDefault('foo'));
    }

    ////////////////////////////////////////////////////////////////////////////
    // lazy setDefault()
    ////////////////////////////////////////////////////////////////////////////

    public function testSetLazyReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->setDefault('foo', function (Options $options): void {}));
    }

    public function testSetLazyClosure(): void
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });

        $this->assertEquals(array('foo' => 'lazy'), $this->resolver->resolve());
    }

    public function testClosureWithoutTypeHintNotInvoked(): void
    {
        $closure = function ($options): void {
            Assert::fail('Should not be called');
        };

        $this->resolver->setDefault('foo', $closure);

        $this->assertSame(array('foo' => $closure), $this->resolver->resolve());
    }

    public function testClosureWithoutParametersNotInvoked(): void
    {
        $closure = function (): void {
            Assert::fail('Should not be called');
        };

        $this->resolver->setDefault('foo', $closure);

        $this->assertSame(array('foo' => $closure), $this->resolver->resolve());
    }

    public function testAccessPreviousDefaultValue(): void
    {
        // defined by superclass
        $this->resolver->setDefault('foo', 'bar');

        // defined by subclass
        $this->resolver->setDefault('foo', function (Options $options, $previousValue) {
            Assert::assertEquals('bar', $previousValue);

            return 'lazy';
        });

        $this->assertEquals(array('foo' => 'lazy'), $this->resolver->resolve());
    }

    public function testAccessPreviousLazyDefaultValue(): void
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'bar';
        });

        // defined by subclass
        $this->resolver->setDefault('foo', function (Options $options, $previousValue) {
            Assert::assertEquals('bar', $previousValue);

            return 'lazy';
        });

        $this->assertEquals(array('foo' => 'lazy'), $this->resolver->resolve());
    }

    public function testPreviousValueIsNotEvaluatedIfNoSecondArgument(): void
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function (): void {
            Assert::fail('Should not be called');
        });

        // defined by subclass, no $previousValue argument defined!
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });

        $this->assertEquals(array('foo' => 'lazy'), $this->resolver->resolve());
    }

    public function testOverwrittenLazyOptionNotEvaluated(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            Assert::fail('Should not be called');
        });

        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testInvokeEachLazyOptionOnlyOnce(): void
    {
        $calls = 0;

        $this->resolver->setDefault('lazy1', function (Options $options) use (&$calls): void {
            Assert::assertSame(1, ++$calls);

            $options['lazy2'];
        });

        $this->resolver->setDefault('lazy2', function (Options $options) use (&$calls): void {
            Assert::assertSame(2, ++$calls);
        });

        $this->resolver->resolve();

        $this->assertSame(2, $calls);
    }

    ////////////////////////////////////////////////////////////////////////////
    // setRequired()/isRequired()/getRequiredOptions()
    ////////////////////////////////////////////////////////////////////////////

    public function testSetRequiredReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->setRequired('foo'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetRequiredFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->setRequired('bar');
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testResolveFailsIfRequiredOptionMissing(): void
    {
        $this->resolver->setRequired('foo');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfRequiredOptionSet(): void
    {
        $this->resolver->setRequired('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfRequiredOptionPassed(): void
    {
        $this->resolver->setRequired('foo');

        $this->assertNotEmpty($this->resolver->resolve(array('foo' => 'bar')));
    }

    public function testIsRequired(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testRequiredIfSetBefore(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));

        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setRequired('foo');

        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testStillRequiredAfterSet(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));

        $this->resolver->setRequired('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testIsNotRequiredAfterRemove(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isRequired('foo'));
    }

    public function testIsNotRequiredAfterClear(): void
    {
        $this->assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isRequired('foo'));
    }

    public function testGetRequiredOptions(): void
    {
        $this->resolver->setRequired(array('foo', 'bar'));
        $this->resolver->setDefault('bam', 'baz');
        $this->resolver->setDefault('foo', 'boo');

        $this->assertSame(array('foo', 'bar'), $this->resolver->getRequiredOptions());
    }

    ////////////////////////////////////////////////////////////////////////////
    // isMissing()/getMissingOptions()
    ////////////////////////////////////////////////////////////////////////////

    public function testIsMissingIfNotSet(): void
    {
        $this->assertFalse($this->resolver->isMissing('foo'));
        $this->resolver->setRequired('foo');
        $this->assertTrue($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingIfSet(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->assertFalse($this->resolver->isMissing('foo'));
        $this->resolver->setRequired('foo');
        $this->assertFalse($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingAfterRemove(): void
    {
        $this->resolver->setRequired('foo');
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingAfterClear(): void
    {
        $this->resolver->setRequired('foo');
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isRequired('foo'));
    }

    public function testGetMissingOptions(): void
    {
        $this->resolver->setRequired(array('foo', 'bar'));
        $this->resolver->setDefault('bam', 'baz');
        $this->resolver->setDefault('foo', 'boo');

        $this->assertSame(array('bar'), $this->resolver->getMissingOptions());
    }

    ////////////////////////////////////////////////////////////////////////////
    // setDefined()/isDefined()/getDefinedOptions()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetDefinedFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->setDefined('bar');
        });

        $this->resolver->resolve();
    }

    public function testDefinedOptionsNotIncludedInResolvedOptions(): void
    {
        $this->resolver->setDefined('foo');

        $this->assertSame(array(), $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfDefaultSetBefore(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefined('foo');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfDefaultSetAfter(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfPassedToResolve(): void
    {
        $this->resolver->setDefined('foo');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve(array('foo' => 'bar')));
    }

    public function testIsDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testLazyOptionsAreDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefault('foo', function (Options $options): void {});
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testRequiredOptionsAreDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setRequired('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testSetOptionsAreDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefault('foo', 'bar');
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testGetDefinedOptions(): void
    {
        $this->resolver->setDefined(array('foo', 'bar'));
        $this->resolver->setDefault('baz', 'bam');
        $this->resolver->setRequired('boo');

        $this->assertSame(array('foo', 'bar', 'baz', 'boo'), $this->resolver->getDefinedOptions());
    }

    public function testRemovedOptionsAreNotDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isDefined('foo'));
    }

    public function testClearedOptionsAreNotDefined(): void
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isDefined('foo'));
    }

    ////////////////////////////////////////////////////////////////////////////
    // setAllowedTypes()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetAllowedTypesFailsIfUnknownOption(): void
    {
        $this->resolver->setAllowedTypes('foo', 'string');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetAllowedTypesFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->setAllowedTypes('bar', 'string');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "int[]", but is of type "DateTime[]".
     */
    public function testResolveFailsIfInvalidTypedArray(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');

        $this->resolver->resolve(array('foo' => array(new \DateTime())));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value "bar" is expected to be of type "int[]", but is of type "string".
     */
    public function testResolveFailsWithNonArray(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');

        $this->resolver->resolve(array('foo' => 'bar'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "int[]", but is of type "integer|stdClass|array|DateTime[]".
     */
    public function testResolveFailsIfTypedArrayContainsInvalidTypes(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');
        $values = range(1, 5);
        $values[] = new \stdClass();
        $values[] = array();
        $values[] = new \DateTime();
        $values[] = 123;

        $this->resolver->resolve(array('foo' => $values));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "int[][]", but is of type "double[][]".
     */
    public function testResolveFailsWithCorrectLevelsButWrongScalar(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');

        $this->resolver->resolve(
            array(
                'foo' => array(
                    array(1.2),
                ),
            )
        );
    }

    /**
     * @dataProvider provideInvalidTypes
     */
    public function testResolveFailsIfInvalidType($actualType, $allowedType, $exceptionMessage): void
    {
        $this->resolver->setDefined('option');
        $this->resolver->setAllowedTypes('option', $allowedType);

        if (method_exists($this, 'expectException')) {
            $this->expectException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException');
            $this->expectExceptionMessage($exceptionMessage);
        } else {
            $this->setExpectedException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException', $exceptionMessage);
        }

        $this->resolver->resolve(array('option' => $actualType));
    }

    public function provideInvalidTypes()
    {
        return array(
            array(true, 'string', 'The option "option" with value true is expected to be of type "string", but is of type "boolean".'),
            array(false, 'string', 'The option "option" with value false is expected to be of type "string", but is of type "boolean".'),
            array(fopen(__FILE__, 'r'), 'string', 'The option "option" with value resource is expected to be of type "string", but is of type "resource".'),
            array(array(), 'string', 'The option "option" with value array is expected to be of type "string", but is of type "array".'),
            array(new OptionsResolver(), 'string', 'The option "option" with value Symfony\Component\OptionsResolver\OptionsResolver is expected to be of type "string", but is of type "Symfony\Component\OptionsResolver\OptionsResolver".'),
            array(42, 'string', 'The option "option" with value 42 is expected to be of type "string", but is of type "integer".'),
            array(null, 'string', 'The option "option" with value null is expected to be of type "string", but is of type "NULL".'),
            array('bar', '\stdClass', 'The option "option" with value "bar" is expected to be of type "\stdClass", but is of type "string".'),
        );
    }

    public function testResolveSucceedsIfValidType(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value 42 is expected to be of type "string" or "bool", but is of type "integer".
     */
    public function testResolveFailsIfInvalidTypeMultiple(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedTypes('foo', array('string', 'bool'));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidTypeMultiple(): void
    {
        $this->resolver->setDefault('foo', true);
        $this->resolver->setAllowedTypes('foo', array('string', 'bool'));

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfInstanceOfClass(): void
    {
        $this->resolver->setDefault('foo', new \stdClass());
        $this->resolver->setAllowedTypes('foo', '\stdClass');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfTypedArray(): void
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedTypes('foo', array('null', 'DateTime[]'));

        $data = array(
            'foo' => array(
                new \DateTime(),
                new \DateTime(),
            ),
        );
        $result = $this->resolver->resolve($data);
        $this->assertEquals($data, $result);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfNotInstanceOfClass(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', '\stdClass');

        $this->resolver->resolve();
    }

    ////////////////////////////////////////////////////////////////////////////
    // addAllowedTypes()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testAddAllowedTypesFailsIfUnknownOption(): void
    {
        $this->resolver->addAllowedTypes('foo', 'string');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfAddAllowedTypesFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->addAllowedTypes('bar', 'string');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedType(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedTypes('foo', 'string');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedType(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedTypes('foo', 'string');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedTypeMultiple(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedTypes('foo', array('string', 'bool'));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedTypeMultiple(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedTypes('foo', array('string', 'bool'));

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');
        $this->resolver->addAllowedTypes('foo', 'bool');

        $this->resolver->setDefault('foo', 'bar');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite2(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');
        $this->resolver->addAllowedTypes('foo', 'bool');

        $this->resolver->setDefault('foo', false);

        $this->assertNotEmpty($this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // setAllowedValues()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetAllowedValuesFailsIfUnknownOption(): void
    {
        $this->resolver->setAllowedValues('foo', 'bar');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetAllowedValuesFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->setAllowedValues('bar', 'baz');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value 42 is invalid. Accepted values are: "bar".
     */
    public function testResolveFailsIfInvalidValue(): void
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->resolver->resolve(array('foo' => 42));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value null is invalid. Accepted values are: "bar".
     */
    public function testResolveFailsIfInvalidValueIsNull(): void
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidValueStrict(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', '42');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->assertEquals(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidValueIsNull(): void
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedValues('foo', null);

        $this->assertEquals(array('foo' => null), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value 42 is invalid. Accepted values are: "bar", false, null.
     */
    public function testResolveFailsIfInvalidValueMultiple(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', array('bar', false, null));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValueMultiple(): void
    {
        $this->resolver->setDefault('foo', 'baz');
        $this->resolver->setAllowedValues('foo', array('bar', 'baz'));

        $this->assertEquals(array('foo' => 'baz'), $this->resolver->resolve());
    }

    public function testResolveFailsIfClosureReturnsFalse(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', function ($value) use (&$passedValue) {
            $passedValue = $value;

            return false;
        });

        try {
            $this->resolver->resolve();
            $this->fail('Should fail');
        } catch (InvalidOptionsException $e) {
        }

        $this->assertSame(42, $passedValue);
    }

    public function testResolveSucceedsIfClosureReturnsTrue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', function ($value) use (&$passedValue) {
            $passedValue = $value;

            return true;
        });

        $this->assertEquals(array('foo' => 'bar'), $this->resolver->resolve());
        $this->assertSame('bar', $passedValue);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfAllClosuresReturnFalse(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', array(
            function () { return false; },
            function () { return false; },
            function () { return false; },
        ));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfAnyClosureReturnsTrue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', array(
            function () { return false; },
            function () { return true; },
            function () { return false; },
        ));

        $this->assertEquals(array('foo' => 'bar'), $this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // addAllowedValues()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testAddAllowedValuesFailsIfUnknownOption(): void
    {
        $this->resolver->addAllowedValues('foo', 'bar');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfAddAllowedValuesFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->addAllowedValues('bar', 'baz');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedValue(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedValues('foo', 'bar');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'bar');

        $this->assertEquals(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidAddedValueIsNull(): void
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->addAllowedValues('foo', null);

        $this->assertEquals(array('foo' => null), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedValueMultiple(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedValues('foo', array('bar', 'baz'));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValueMultiple(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedValues('foo', array('bar', 'baz'));

        $this->assertEquals(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'baz');

        $this->assertEquals(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite2(): void
    {
        $this->resolver->setDefault('foo', 'baz');
        $this->resolver->setAllowedValues('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'baz');

        $this->assertEquals(array('foo' => 'baz'), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfAllAddedClosuresReturnFalse(): void
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', function () { return false; });
        $this->resolver->addAllowedValues('foo', function () { return false; });

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfAnyAddedClosureReturnsTrue(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', function () { return false; });
        $this->resolver->addAllowedValues('foo', function () { return true; });

        $this->assertEquals(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testResolveSucceedsIfAnyAddedClosureReturnsTrue2(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', function () { return true; });
        $this->resolver->addAllowedValues('foo', function () { return false; });

        $this->assertEquals(array('foo' => 'bar'), $this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // setNormalizer()
    ////////////////////////////////////////////////////////////////////////////

    public function testSetNormalizerReturnsThis(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->assertSame($this->resolver, $this->resolver->setNormalizer('foo', function (): void {}));
    }

    public function testSetNormalizerClosure(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function () {
            return 'normalized';
        });

        $this->assertEquals(array('foo' => 'normalized'), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetNormalizerFailsIfUnknownOption(): void
    {
        $this->resolver->setNormalizer('foo', function (): void {});
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetNormalizerFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->setNormalizer('foo', function (): void {});
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testNormalizerReceivesSetOption(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized['.$value.']';
        });

        $this->assertEquals(array('foo' => 'normalized[bar]'), $this->resolver->resolve());
    }

    public function testNormalizerReceivesPassedOption(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized['.$value.']';
        });

        $resolved = $this->resolver->resolve(array('foo' => 'baz'));

        $this->assertEquals(array('foo' => 'normalized[baz]'), $resolved);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateTypeBeforeNormalization(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setAllowedTypes('foo', 'int');

        $this->resolver->setNormalizer('foo', function (): void {
            Assert::fail('Should not be called.');
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateValueBeforeNormalization(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setAllowedValues('foo', 'baz');

        $this->resolver->setNormalizer('foo', function (): void {
            Assert::fail('Should not be called.');
        });

        $this->resolver->resolve();
    }

    public function testNormalizerCanAccessOtherOptions(): void
    {
        $this->resolver->setDefault('default', 'bar');
        $this->resolver->setDefault('norm', 'baz');

        $this->resolver->setNormalizer('norm', function (Options $options) {
            /* @var TestCase $test */
            Assert::assertSame('bar', $options['default']);

            return 'normalized';
        });

        $this->assertEquals(array(
            'default' => 'bar',
            'norm' => 'normalized',
        ), $this->resolver->resolve());
    }

    public function testNormalizerCanAccessLazyOptions(): void
    {
        $this->resolver->setDefault('lazy', function (Options $options) {
            return 'bar';
        });
        $this->resolver->setDefault('norm', 'baz');

        $this->resolver->setNormalizer('norm', function (Options $options) {
            /* @var TestCase $test */
            Assert::assertEquals('bar', $options['lazy']);

            return 'normalized';
        });

        $this->assertEquals(array(
            'lazy' => 'bar',
            'norm' => 'normalized',
        ), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailIfCyclicDependencyBetweenNormalizers(): void
    {
        $this->resolver->setDefault('norm1', 'bar');
        $this->resolver->setDefault('norm2', 'baz');

        $this->resolver->setNormalizer('norm1', function (Options $options): void {
            $options['norm2'];
        });

        $this->resolver->setNormalizer('norm2', function (Options $options): void {
            $options['norm1'];
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailIfCyclicDependencyBetweenNormalizerAndLazyOption(): void
    {
        $this->resolver->setDefault('lazy', function (Options $options): void {
            $options['norm'];
        });

        $this->resolver->setDefault('norm', 'baz');

        $this->resolver->setNormalizer('norm', function (Options $options): void {
            $options['lazy'];
        });

        $this->resolver->resolve();
    }

    public function testCaughtExceptionFromNormalizerDoesNotCrashOptionResolver(): void
    {
        $throw = true;

        $this->resolver->setDefaults(array('catcher' => null, 'thrower' => null));

        $this->resolver->setNormalizer('catcher', function (Options $options) {
            try {
                return $options['thrower'];
            } catch (\Exception $e) {
                return false;
            }
        });

        $this->resolver->setNormalizer('thrower', function () use (&$throw) {
            if ($throw) {
                $throw = false;
                throw new \UnexpectedValueException('throwing');
            }

            return true;
        });

        $this->assertSame(array('catcher' => false, 'thrower' => true), $this->resolver->resolve());
    }

    public function testCaughtExceptionFromLazyDoesNotCrashOptionResolver(): void
    {
        $throw = true;

        $this->resolver->setDefault('catcher', function (Options $options) {
            try {
                return $options['thrower'];
            } catch (\Exception $e) {
                return false;
            }
        });

        $this->resolver->setDefault('thrower', function (Options $options) use (&$throw) {
            if ($throw) {
                $throw = false;
                throw new \UnexpectedValueException('throwing');
            }

            return true;
        });

        $this->assertSame(array('catcher' => false, 'thrower' => true), $this->resolver->resolve());
    }

    public function testInvokeEachNormalizerOnlyOnce(): void
    {
        $calls = 0;

        $this->resolver->setDefault('norm1', 'bar');
        $this->resolver->setDefault('norm2', 'baz');

        $this->resolver->setNormalizer('norm1', function ($options) use (&$calls): void {
            Assert::assertSame(1, ++$calls);

            $options['norm2'];
        });
        $this->resolver->setNormalizer('norm2', function () use (&$calls): void {
            Assert::assertSame(2, ++$calls);
        });

        $this->resolver->resolve();

        $this->assertSame(2, $calls);
    }

    public function testNormalizerNotCalledForUnsetOptions(): void
    {
        $this->resolver->setDefined('norm');

        $this->resolver->setNormalizer('norm', function (): void {
            Assert::fail('Should not be called.');
        });

        $this->assertEmpty($this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // setDefaults()
    ////////////////////////////////////////////////////////////////////////////

    public function testSetDefaultsReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->setDefaults(array('foo', 'bar')));
    }

    public function testSetDefaults(): void
    {
        $this->resolver->setDefault('one', '1');
        $this->resolver->setDefault('two', 'bar');

        $this->resolver->setDefaults(array(
            'two' => '2',
            'three' => '3',
        ));

        $this->assertEquals(array(
            'one' => '1',
            'two' => '2',
            'three' => '3',
        ), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetDefaultsFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->setDefaults(array('two' => '2'));
        });

        $this->resolver->resolve();
    }

    ////////////////////////////////////////////////////////////////////////////
    // remove()
    ////////////////////////////////////////////////////////////////////////////

    public function testRemoveReturnsThis(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame($this->resolver, $this->resolver->remove('foo'));
    }

    public function testRemoveSingleOption(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefault('baz', 'boo');
        $this->resolver->remove('foo');

        $this->assertSame(array('baz' => 'boo'), $this->resolver->resolve());
    }

    public function testRemoveMultipleOptions(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefault('baz', 'boo');
        $this->resolver->setDefault('doo', 'dam');

        $this->resolver->remove(array('foo', 'doo'));

        $this->assertSame(array('baz' => 'boo'), $this->resolver->resolve());
    }

    public function testRemoveLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });
        $this->resolver->remove('foo');

        $this->assertSame(array(), $this->resolver->resolve());
    }

    public function testRemoveNormalizer(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testRemoveAllowedTypes(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'int');
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testRemoveAllowedValues(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', array('baz', 'boo'));
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfRemoveFromLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->remove('bar');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testRemoveUnknownOptionIgnored(): void
    {
        $this->assertNotNull($this->resolver->remove('foo'));
    }

    ////////////////////////////////////////////////////////////////////////////
    // clear()
    ////////////////////////////////////////////////////////////////////////////

    public function testClearReturnsThis(): void
    {
        $this->assertSame($this->resolver, $this->resolver->clear());
    }

    public function testClearRemovesAllOptions(): void
    {
        $this->resolver->setDefault('one', 1);
        $this->resolver->setDefault('two', 2);

        $this->resolver->clear();

        $this->assertEmpty($this->resolver->resolve());
    }

    public function testClearLazyOption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });
        $this->resolver->clear();

        $this->assertSame(array(), $this->resolver->resolve());
    }

    public function testClearNormalizer(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testClearAllowedTypes(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'int');
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }

    public function testClearAllowedValues(): void
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'baz');
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(array('foo' => 'bar'), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfClearFromLazyption(): void
    {
        $this->resolver->setDefault('foo', function (Options $options): void {
            $options->clear();
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testClearOptionAndNormalizer(): void
    {
        $this->resolver->setDefault('foo1', 'bar');
        $this->resolver->setNormalizer('foo1', function (Options $options) {
            return '';
        });
        $this->resolver->setDefault('foo2', 'bar');
        $this->resolver->setNormalizer('foo2', function (Options $options) {
            return '';
        });

        $this->resolver->clear();
        $this->assertEmpty($this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // ArrayAccess
    ////////////////////////////////////////////////////////////////////////////

    public function testArrayAccess(): void
    {
        $this->resolver->setDefault('default1', 0);
        $this->resolver->setDefault('default2', 1);
        $this->resolver->setRequired('required');
        $this->resolver->setDefined('defined');
        $this->resolver->setDefault('lazy1', function (Options $options) {
            return 'lazy';
        });

        $this->resolver->setDefault('lazy2', function (Options $options): void {
            Assert::assertTrue(isset($options['default1']));
            Assert::assertTrue(isset($options['default2']));
            Assert::assertTrue(isset($options['required']));
            Assert::assertTrue(isset($options['lazy1']));
            Assert::assertTrue(isset($options['lazy2']));
            Assert::assertFalse(isset($options['defined']));

            Assert::assertSame(0, $options['default1']);
            Assert::assertSame(42, $options['default2']);
            Assert::assertSame('value', $options['required']);
            Assert::assertSame('lazy', $options['lazy1']);

            // Obviously $options['lazy'] and $options['defined'] cannot be
            // accessed
        });

        $this->resolver->resolve(array('default2' => 42, 'required' => 'value'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testArrayAccessGetFailsOutsideResolve(): void
    {
        $this->resolver->setDefault('default', 0);

        $this->resolver['default'];
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testArrayAccessExistsFailsOutsideResolve(): void
    {
        $this->resolver->setDefault('default', 0);

        isset($this->resolver['default']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testArrayAccessSetNotSupported(): void
    {
        $this->resolver['default'] = 0;
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testArrayAccessUnsetNotSupported(): void
    {
        $this->resolver->setDefault('default', 0);

        unset($this->resolver['default']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoSuchOptionException
     * @expectedExceptionMessage The option "undefined" does not exist. Defined options are: "foo", "lazy".
     */
    public function testFailIfGetNonExisting(): void
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setDefault('lazy', function (Options $options): void {
            $options['undefined'];
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoSuchOptionException
     * @expectedExceptionMessage The optional option "defined" has no value set. You should make sure it is set with "isset" before reading it.
     */
    public function testFailIfGetDefinedButUnset(): void
    {
        $this->resolver->setDefined('defined');

        $this->resolver->setDefault('lazy', function (Options $options): void {
            $options['defined'];
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailIfCyclicDependency(): void
    {
        $this->resolver->setDefault('lazy1', function (Options $options): void {
            $options['lazy2'];
        });

        $this->resolver->setDefault('lazy2', function (Options $options): void {
            $options['lazy1'];
        });

        $this->resolver->resolve();
    }

    ////////////////////////////////////////////////////////////////////////////
    // Countable
    ////////////////////////////////////////////////////////////////////////////

    public function testCount(): void
    {
        $this->resolver->setDefault('default', 0);
        $this->resolver->setRequired('required');
        $this->resolver->setDefined('defined');
        $this->resolver->setDefault('lazy1', function (): void {});

        $this->resolver->setDefault('lazy2', function (Options $options): void {
            Assert::assertCount(4, $options);
        });

        $this->assertCount(4, $this->resolver->resolve(array('required' => 'value')));
    }

    /**
     * In resolve() we count the options that are actually set (which may be
     * only a subset of the defined options). Outside of resolve(), it's not
     * clear what is counted.
     *
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testCountFailsOutsideResolve(): void
    {
        $this->resolver->setDefault('foo', 0);
        $this->resolver->setRequired('bar');
        $this->resolver->setDefined('bar');
        $this->resolver->setDefault('lazy1', function (): void {});

        count($this->resolver);
    }
}
