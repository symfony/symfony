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
        $test = $this;

        $this->options->set('foo', function (Options $options) use ($test) {
           return 'dynamic';
        });

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
            }
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
