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
     * @expectedException Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testSetNotSupportedAfterGet()
    {
        $this->options->set('foo', 'bar');
        $this->options->get('foo');
        $this->options->set('foo', 'baz');
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testRemoveNotSupportedAfterGet()
    {
        $this->options->set('foo', 'bar');
        $this->options->get('foo');
        $this->options->remove('foo');
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

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
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
}
