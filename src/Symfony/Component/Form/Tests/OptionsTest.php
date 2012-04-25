<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\Options;

class OptionsTest extends \PHPUnit_Framework_TestCase
{
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

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetNonExisting()
    {
        $this->options['foo'];
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\OptionDefinitionException
     */
    public function testSetNotSupportedAfterGet()
    {
        $this->options['foo'] = 'bar';
        $this->options['foo'];
        $this->options['foo'] = 'baz';
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\OptionDefinitionException
     */
    public function testUnsetNotSupportedAfterGet()
    {
        $this->options['foo'] = 'bar';
        $this->options['foo'];
        unset($this->options['foo']);
    }

    public function testLazyOption()
    {
        $test = $this;

        $this->options['foo'] = function (Options $options) use ($test) {
           return 'dynamic';
        };

        $this->assertEquals('dynamic', $this->options['foo']);
    }

    public function testLazyOptionWithEagerCurrentValue()
    {
        $test = $this;

        // defined by superclass
        $this->options['foo'] = 'bar';

        // defined by subclass
        $this->options['foo'] = function (Options $options, $currentValue) use ($test) {
           $test->assertEquals('bar', $currentValue);

           return 'dynamic';
        };

        $this->assertEquals('dynamic', $this->options['foo']);
    }

    public function testLazyOptionWithLazyCurrentValue()
    {
        $test = $this;

        // defined by superclass
        $this->options['foo'] = function (Options $options) {
            return 'bar';
        };

        // defined by subclass
        $this->options['foo'] = function (Options $options, $currentValue) use ($test) {
           $test->assertEquals('bar', $currentValue);

           return 'dynamic';
        };

        $this->assertEquals('dynamic', $this->options['foo']);
    }

    public function testLazyOptionWithEagerDependency()
    {
        $test = $this;

        $this->options['foo'] = 'bar';

        $this->options['bam'] = function (Options $options) use ($test) {
            $test->assertEquals('bar', $options['foo']);

            return 'dynamic';
        };

        $this->assertEquals('bar', $this->options['foo']);
        $this->assertEquals('dynamic', $this->options['bam']);
    }

    public function testLazyOptionWithLazyDependency()
    {
        $test = $this;

        $this->options['foo'] = function (Options $options) {
            return 'bar';
        };

        $this->options['bam'] = function (Options $options) use ($test) {
            $test->assertEquals('bar', $options['foo']);

            return 'dynamic';
        };

        $this->assertEquals('bar', $this->options['foo']);
        $this->assertEquals('dynamic', $this->options['bam']);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\OptionDefinitionException
     */
    public function testLazyOptionDisallowCyclicDependencies()
    {
        $this->options['foo'] = function (Options $options) {
            $options['bam'];
        };

        $this->options['bam'] = function (Options $options) {
            $options['foo'];
        };

        $this->options['foo'];
    }
}
