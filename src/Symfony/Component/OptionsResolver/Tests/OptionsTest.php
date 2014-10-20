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

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsConfig;

class OptionsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Options
     */
    private $options;

    protected function setUp()
    {
        $this->options = new Options();
    }

    public function testResolve()
    {
        $defaults = array(
            'one' => '1',
            'two' => '2',
        );

        $options = array(
            'two' => '20',
        );

        $this->assertEquals(array(
            'one' => '1',
            'two' => '20',
        ), Options::resolve($options, $defaults));
    }

    public function testResolveNumericOptions()
    {
        $defaults = array(
            '1' => '1',
            '2' => '2',
        );

        $options = array(
            '2' => '20',
        );

        $this->assertEquals(array(
            '1' => '1',
            '2' => '20',
        ), Options::resolve($options, $defaults));
    }

    public function testResolveLazy()
    {
        $defaults = new Options(array(
            'one' => '1',
            'two' => function (Options $options) {
                return '20';
            },
        ));

        $options = array();

        $this->assertEquals(array(
            'one' => '1',
            'two' => '20',
        ), Options::resolve($options, $defaults));
    }

    public function testResolveConfig()
    {
        $config = new OptionsConfig();

        $config->setDefaults(array(
            'one' => '1',
            'two' => '2',
        ));

        $options = array(
            'two' => '20',
        );

        $this->assertEquals(array(
            'one' => '1',
            'two' => '20',
        ), Options::resolve($options, $config));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfNonExistingOption()
    {
        $defaults = array(
            'one' => '1',
        );

        $options = array(
            'foo' => 'bar',
        );

        Options::resolve($options, $defaults);
    }

    public function testValidateNamesSucceedsIfValidOption()
    {
        $options = array(
            'one' => '1',
        );

        Options::validateNames($options, 'one');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateNamesFailsIfNonExistingOption()
    {
        $options = array(
            'foo' => 'bar',
        );

        Options::validateNames($options, 'one');
    }

    public function testValidateNamesSucceedsIfValidOptions()
    {
        $options = array(
            'one' => '1',
        );

        Options::validateNames($options, array(
            'one',
            'two',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateNamesFailsIfNonExistingOptions()
    {
        $options = array(
            'one' => '1',
            'foo' => 'bar',
        );

        Options::validateNames($options, array(
            'one',
            'two',
        ));
    }

    public function testValidateNamesSucceedsIfValidOptionsNamesAsKeys()
    {
        $options = array(
            'one' => '1',
        );

        Options::validateNames($options, array(
            'one' => null,
            'two' => null,
        ), true);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateNamesFailsIfNonExistingOptionsNamesAsKeys()
    {
        $options = array(
            'one' => '1',
            'foo' => 'bar',
        );

        Options::validateNames($options, array(
            'one' => null,
            'two' => null,
        ), true);
    }

    public function testValidateRequiredSucceedsIfRequiredOptionPresent()
    {
        $options = array(
            'one' => '10',
        );

        Options::validateRequired($options, 'one');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testValidateRequiredFailsIfMissingRequiredOption()
    {
        $options = array(
            'two' => '20',
        );

        Options::validateRequired($options, 'one');
    }

    public function testValidateRequiredSucceedsIfRequiredOptionsPresent()
    {
        $options = array(
            'one' => '10',
            'two' => '20',
        );

        Options::validateRequired($options, array(
            'one',
            'two',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testValidateRequiredFailsIfMissingRequiredOptions()
    {
        $options = array(
            'two' => '20',
        );

        Options::validateRequired($options, array(
            'one',
            'two',
        ));
    }

    public function testValidateRequiredSucceedsIfRequiredOptionsPresentNamesAsKeys()
    {
        $options = array(
            'one' => '10',
            'two' => '20',
        );

        Options::validateRequired($options, array(
            'one' => null,
            'two' => null,
        ), true);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testValidateRequiredFailsIfMissingRequiredOptionsNamesAsKeys()
    {
        $options = array(
            'two' => '20',
        );

        Options::validateRequired($options, array(
            'one' => null,
            'two' => null,
        ), true);
    }

    public function testValidateTypesSucceedsIfValidType()
    {
        $options = array(
            'one' => 'one',
        );

        Options::validateTypes($options, array(
            'one' => 'string',
        ));
    }

    public function testValidateTypesSucceedsIfValidTypePassArray()
    {
        $options = array(
            'one' => 'one',
        );

        Options::validateTypes($options, array(
            'one' => array('string', 'bool'),
        ));
    }

    public function testValidateTypesSucceedsIfValidTypePassObject()
    {
        $object = new \stdClass();
        $options = array(
            'one' => $object,
        );

        Options::validateTypes($options, array(
            'one' => 'object',
        ));
    }

    public function testValidateTypesSucceedsIfValidTypePassClass()
    {
        $object = new \stdClass();
        $options = array(
            'one' => $object,
        );

        Options::validateTypes($options, array(
            'one' => '\stdClass',
        ));
    }

    public function testValidateTypesSucceedsIfValidTypePassArrayOfType()
    {
        $intArray = array(1, 2, 3);
        $stringArray = array('one', 'two', 'three');
        $stdClassArray = array(new \stdClass(), new \stdClass(), new \stdClass());
        $mixedArray = array(new \stdClass(), 'foobar', 1.23);
        $keyValueArray = array('one' => 1, 'two' => 2, 'three' => 3);
        $deepArray = array('deeper' => $keyValueArray);
        $options = array(
            'integer_array' => $intArray,
            'string_array' => $stringArray,
            'object_array' => $stdClassArray,
            'stdclass_array' => $stdClassArray,
            'mixed_array' => $mixedArray,
            'keyvalue_array' => $keyValueArray,
            'deep_array' => $deepArray,
        );

        Options::validateTypes($options, array(
            'int_array' => 'int[]',
            'string_array' => 'string[]',
            'object_array' => 'object[]',
            'stdclass_array' => 'stdClass[]',
            'mixed_array' => 'mixed[]',
        ));

        Options::validateTypes($options, array(
            'int_array' => 'array<int>',
            'string_array' => 'array<string>',
            'object_array' => 'array<object>',
            'stdclass_array' => 'array<stdClass>',
            'mixed_array' => 'array<mixed>',
            'keyvalue_array' => 'array<string,int>',
            'deep_array' => 'array<string,array<string,int>>',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateTypesFailsIfInvalidType()
    {
        $options = array(
            'one' => 1.23,
        );

        Options::validateTypes($options, array(
            'one' => array('string', 'bool', 'float[]'),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateTypesFailsIfInvalidArrayOfTypes()
    {
        $options = array(
            'one' => array(
                1.23,
            ),
        );

        Options::validateTypes($options, array(
            'one' => array('string', 'bool', 'float'),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateTypesFailsIfInvalidTypeMultipleOptions()
    {
        $options = array(
            'one' => 'foo',
            'two' => 1.23,
        );

        Options::validateTypes($options, array(
            'one' => 'string',
            'two' => 'bool',
        ));
    }

    public function testValidateValuesSucceedsIfValidValue()
    {
        $options = array(
            'one' => 'one',
        );

        Options::validateValues($options, array(
            'one' => array('1', 'one'),
        ));
    }

    public function testValidateValuesSucceedsIfValidValueMultipleOptions()
    {
        $options = array(
            'one' => '1',
            'two' => 'two',
        );

        Options::validateValues($options, array(
            'one' => array('1', 'one'),
            'two' => array('2', 'two'),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateValuesFailsIfInvalidValue()
    {
        $options = array(
            'one' => '2',
        );

        Options::validateValues($options, array(
            'one' => array('1', 'one'),
        ));
    }

    public function testValidateValuesSucceedsIfValidValueCallback()
    {
        $options = array(
            'test' => true,
        );

        Options::validateValues($options, array(
            'test' => function ($value) {
                return true;
            },
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateValuesFailsIfInvalidValueCallback()
    {
        $options = array(
            'test' => true,
        );

        Options::validateValues($options, array(
            'test' => function ($value) {
                return false;
            },
        ));
    }

    public function testArrayAccess()
    {
        $this->assertFalse(isset($this->options['foo']));
        $this->assertFalse(isset($this->options['bar']));

        $this->options['foo'] = 0;
        $this->options['bar'] = 1;

        $this->assertTrue(isset($this->options['foo']));
        $this->assertTrue(isset($this->options['bar']));

        unset($this->options['bar']);

        $this->assertTrue(isset($this->options['foo']));
        $this->assertFalse(isset($this->options['bar']));
        $this->assertEquals(0, $this->options['foo']);
    }

    public function testCountable()
    {
        $this->options->set('foo', 0);
        $this->options->set('bar', 1);

        $this->assertCount(2, $this->options);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetNonExisting()
    {
        $this->options->get('foo');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testSetNotSupportedAfterGet()
    {
        $this->options->set('foo', 'bar');
        $this->options->get('foo');
        $this->options->set('foo', 'baz');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testRemoveNotSupportedAfterGet()
    {
        $this->options->set('foo', 'bar');
        $this->options->get('foo');
        $this->options->remove('foo');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testSetNormalizerNotSupportedAfterGet()
    {
        $this->options->set('foo', 'bar');
        $this->options->get('foo');
        $this->options->setNormalizer('foo', function () {});
    }

    public function testSetLazyOption()
    {
        $this->options->set('foo', function (Options $options) {
           return 'dynamic';
        });

        $this->assertEquals('dynamic', $this->options->get('foo'));
    }

    public static function getLazyOptionStatic(Options $options)
    {
        return 'dynamic';
    }

    public function testSetLazyOptionToClassMethod()
    {
        $this->options->set('foo', array(__CLASS__, 'getLazyOptionStatic'));

        $this->assertEquals('dynamic', $this->options->get('foo'));
    }

    public static function getLazyOption(Options $options)
    {
        return 'dynamic';
    }

    public function testSetLazyOptionToInstanceMethod()
    {
        $this->options->set('foo', array($this, 'getLazyOption'));

        $this->assertEquals('dynamic', $this->options->get('foo'));
    }

    public function testSetDiscardsPreviousValue()
    {
        $test = $this;

        // defined by superclass
        $this->options->set('foo', 'bar');

        // defined by subclass
        $this->options->set('foo', function (Options $options, $previousValue) use ($test) {
            /* @var \PHPUnit_Framework_TestCase $test */
            $test->assertNull($previousValue);

            return 'dynamic';
        });

        $this->assertEquals('dynamic', $this->options->get('foo'));
    }

    public function testOverloadKeepsPreviousValue()
    {
        $test = $this;

        // defined by superclass
        $this->options->set('foo', 'bar');

        // defined by subclass
        $this->options->overload('foo', function (Options $options, $previousValue) use ($test) {
            /* @var \PHPUnit_Framework_TestCase $test */
            $test->assertEquals('bar', $previousValue);

            return 'dynamic';
        });

        $this->assertEquals('dynamic', $this->options->get('foo'));
    }

    public function testPreviousValueIsEvaluatedIfLazy()
    {
        $test = $this;

        // defined by superclass
        $this->options->set('foo', function (Options $options) {
            return 'bar';
        });

        // defined by subclass
        $this->options->overload('foo', function (Options $options, $previousValue) use ($test) {
            /* @var \PHPUnit_Framework_TestCase $test */
            $test->assertEquals('bar', $previousValue);

            return 'dynamic';
        });

        $this->assertEquals('dynamic', $this->options->get('foo'));
    }

    public function testPreviousValueIsNotEvaluatedIfNoSecondArgument()
    {
        $test = $this;

        // defined by superclass
        $this->options->set('foo', function (Options $options) use ($test) {
            $test->fail('Should not be called');
        });

        // defined by subclass, no $previousValue argument defined!
        $this->options->overload('foo', function (Options $options) use ($test) {
            return 'dynamic';
        });

        $this->assertEquals('dynamic', $this->options->get('foo'));
    }

    public function testLazyOptionCanAccessOtherOptions()
    {
        $test = $this;

        $this->options->set('foo', 'bar');

        $this->options->set('bam', function (Options $options) use ($test) {
            /* @var \PHPUnit_Framework_TestCase $test */
            $test->assertEquals('bar', $options->get('foo'));

            return 'dynamic';
        });

        $this->assertEquals('bar', $this->options->get('foo'));
        $this->assertEquals('dynamic', $this->options->get('bam'));
    }

    public function testLazyOptionCanAccessOtherLazyOptions()
    {
        $test = $this;

        $this->options->set('foo', function (Options $options) {
            return 'bar';
        });

        $this->options->set('bam', function (Options $options) use ($test) {
            /* @var \PHPUnit_Framework_TestCase $test */
            $test->assertEquals('bar', $options->get('foo'));

            return 'dynamic';
        });

        $this->assertEquals('bar', $this->options->get('foo'));
        $this->assertEquals('dynamic', $this->options->get('bam'));
    }

    public function testNormalizer()
    {
        $this->options->set('foo', 'bar');

        $this->options->setNormalizer('foo', function () {
            return 'normalized';
        });

        $this->assertEquals('normalized', $this->options->get('foo'));
    }

    public function testNormalizerReceivesUnnormalizedValue()
    {
        $this->options->set('foo', 'bar');

        $this->options->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized['.$value.']';
        });

        $this->assertEquals('normalized[bar]', $this->options->get('foo'));
    }

    public function testNormalizerCanAccessOtherOptions()
    {
        $test = $this;

        $this->options->set('foo', 'bar');
        $this->options->set('bam', 'baz');

        $this->options->setNormalizer('bam', function (Options $options) use ($test) {
            /* @var \PHPUnit_Framework_TestCase $test */
            $test->assertEquals('bar', $options->get('foo'));

            return 'normalized';
        });

        $this->assertEquals('bar', $this->options->get('foo'));
        $this->assertEquals('normalized', $this->options->get('bam'));
    }

    public function testNormalizerCanAccessOtherLazyOptions()
    {
        $test = $this;

        $this->options->set('foo', function (Options $options) {
            return 'bar';
        });
        $this->options->set('bam', 'baz');

        $this->options->setNormalizer('bam', function (Options $options) use ($test) {
            /* @var \PHPUnit_Framework_TestCase $test */
            $test->assertEquals('bar', $options->get('foo'));

            return 'normalized';
        });

        $this->assertEquals('bar', $this->options->get('foo'));
        $this->assertEquals('normalized', $this->options->get('bam'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailForCyclicDependencies()
    {
        $this->options->set('foo', function (Options $options) {
            $options->get('bam');
        });

        $this->options->set('bam', function (Options $options) {
            $options->get('foo');
        });

        $this->options->get('foo');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailForCyclicDependenciesBetweenNormalizers()
    {
        $this->options->set('foo', 'bar');
        $this->options->set('bam', 'baz');

        $this->options->setNormalizer('foo', function (Options $options) {
            $options->get('bam');
        });

        $this->options->setNormalizer('bam', function (Options $options) {
            $options->get('foo');
        });

        $this->options->get('foo');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailForCyclicDependenciesBetweenNormalizerAndLazyOption()
    {
        $this->options->set('foo', function (Options $options) {
            $options->get('bam');
        });
        $this->options->set('bam', 'baz');

        $this->options->setNormalizer('bam', function (Options $options) {
            $options->get('foo');
        });

        $this->options->get('foo');
    }

    public function testAllInvokesEachLazyOptionOnlyOnce()
    {
        $test = $this;
        $i = 1;

        $this->options->set('foo', function (Options $options) use ($test, &$i) {
            $test->assertSame(1, $i);
            ++$i;

            // Implicitly invoke lazy option for "bam"
            $options->get('bam');
        });
        $this->options->set('bam', function (Options $options) use ($test, &$i) {
            $test->assertSame(2, $i);
            ++$i;
        });

        $this->options->all();
    }

    public function testAllInvokesEachNormalizerOnlyOnce()
    {
        $test = $this;
        $i = 1;

        $this->options->set('foo', 'bar');
        $this->options->set('bam', 'baz');

        $this->options->setNormalizer('foo', function (Options $options) use ($test, &$i) {
            $test->assertSame(1, $i);
            ++$i;

            // Implicitly invoke normalizer for "bam"
            $options->get('bam');
        });
        $this->options->setNormalizer('bam', function (Options $options) use ($test, &$i) {
            $test->assertSame(2, $i);
            ++$i;
        });

        $this->options->all();
    }

    public function testReplaceClearsAndSets()
    {
        $this->options->set('one', '1');

        $this->options->replace(array(
            'two' => '2',
            'three' => function (Options $options) {
                return '2' === $options['two'] ? '3' : 'foo';
            },
        ));

        $this->assertEquals(array(
            'two' => '2',
            'three' => '3',
        ), $this->options->all());
    }

    public function testClearRemovesAllOptions()
    {
        $this->options->set('one', 1);
        $this->options->set('two', 2);

        $this->options->clear();

        $this->assertEmpty($this->options->all());
    }

    /**
     * @covers Symfony\Component\OptionsResolver\Options::replace
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testCannotReplaceAfterOptionWasRead()
    {
        $this->options->set('one', 1);
        $this->options->all();

        $this->options->replace(array(
            'two' => '2',
        ));
    }

    /**
     * @covers Symfony\Component\OptionsResolver\Options::overload
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testCannotOverloadAfterOptionWasRead()
    {
        $this->options->set('one', 1);
        $this->options->all();

        $this->options->overload('one', 2);
    }

    /**
     * @covers Symfony\Component\OptionsResolver\Options::clear
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testCannotClearAfterOptionWasRead()
    {
        $this->options->set('one', 1);
        $this->options->all();

        $this->options->clear();
    }

    public function testOverloadCannotBeEvaluatedLazilyWithoutExpectedClosureParams()
    {
        $this->options->set('foo', 'bar');

        $this->options->overload('foo', function () {
            return 'test';
        });

        $this->assertNotEquals('test', $this->options->get('foo'));
        $this->assertTrue(is_callable($this->options->get('foo')));
    }

    public function testOverloadCannotBeEvaluatedLazilyWithoutFirstParamTypeHint()
    {
        $this->options->set('foo', 'bar');

        $this->options->overload('foo', function ($object) {
            return 'test';
        });

        $this->assertNotEquals('test', $this->options->get('foo'));
        $this->assertTrue(is_callable($this->options->get('foo')));
    }

    public function testOptionsIteration()
    {
        $this->options->set('foo', 'bar');
        $this->options->set('foo1', 'bar1');
        $expectedResult = array('foo' => 'bar', 'foo1' => 'bar1');

        $this->assertEquals($expectedResult, iterator_to_array($this->options, true));
    }

    public function testHasWithNullValue()
    {
        $this->options->set('foo', null);

        $this->assertTrue($this->options->has('foo'));
    }

    public function testRemoveOptionAndNormalizer()
    {
        $this->options->set('foo1', 'bar');
        $this->options->setNormalizer('foo1', function (Options $options) {
            return '';
        });
        $this->options->set('foo2', 'bar');
        $this->options->setNormalizer('foo2', function (Options $options) {
            return '';
        });

        $this->options->remove('foo2');
        $this->assertEquals(array('foo1' => ''), $this->options->all());
    }

    public function testReplaceOptionAndNormalizer()
    {
        $this->options->set('foo1', 'bar');
        $this->options->setNormalizer('foo1', function (Options $options) {
            return '';
        });
        $this->options->set('foo2', 'bar');
        $this->options->setNormalizer('foo2', function (Options $options) {
            return '';
        });

        $this->options->replace(array('foo1' => 'new'));
        $this->assertEquals(array('foo1' => 'new'), $this->options->all());
    }

    public function testClearOptionAndNormalizer()
    {
        $this->options->set('foo1', 'bar');
        $this->options->setNormalizer('foo1', function (Options $options) {
            return '';
        });
        $this->options->set('foo2', 'bar');
        $this->options->setNormalizer('foo2', function (Options $options) {
            return '';
        });

        $this->options->clear();
        $this->assertEmpty($this->options->all());
    }

    public function testNormalizerWithoutCorrespondingOption()
    {
        $test = $this;

        $this->options->setNormalizer('foo', function (Options $options, $previousValue) use ($test) {
            $test->assertNull($previousValue);

            return '';
        });
        $this->assertEquals(array('foo' => ''), $this->options->all());
    }
}
