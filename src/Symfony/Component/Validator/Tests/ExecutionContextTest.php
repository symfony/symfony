<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use Symfony\Component\Validator\GlobalExecutionContext;

use Symfony\Component\Validator\ConstraintViolation;

use Symfony\Component\Validator\ConstraintViolationList;

use Symfony\Component\Validator\ExecutionContext;

class ExecutionContextTest extends \PHPUnit_Framework_TestCase
{
    protected $walker;
    protected $metadataFactory;
    protected $globalContext;
    protected $context;

    protected function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $this->globalContext = new GlobalExecutionContext('Root', $this->walker, $this->metadataFactory);
        $this->context = new ExecutionContext($this->globalContext, 'currentValue', 'foo.bar', 'Group', 'ClassName', 'propertyName');
    }

    protected function tearDown()
    {
        $this->globalContext = null;
        $this->context = null;
    }

    public function testInit()
    {
        $this->assertCount(0, $this->context->getViolations());
        $this->assertSame('Root', $this->context->getRoot());
        $this->assertSame('foo.bar', $this->context->getPropertyPath());
        $this->assertSame('ClassName', $this->context->getCurrentClass());
        $this->assertSame('propertyName', $this->context->getCurrentProperty());
        $this->assertSame('Group', $this->context->getGroup());
        $this->assertSame($this->walker, $this->context->getGraphWalker());
        $this->assertSame($this->metadataFactory, $this->context->getMetadataFactory());
    }

    public function testClone()
    {
        $clone = clone $this->context;

        $this->assertNotSame($this->context->getViolations(), $clone->getViolations());
    }

    public function testAddViolation()
    {
        $this->context->addViolation('Error', array('foo' => 'bar'), 'invalid');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar',
                'invalid'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationUsesPreconfiguredValueIfNotPassed()
    {
        $this->context->addViolation('Error');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Error',
                array(),
                'Root',
                'foo.bar',
                'currentValue'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationUsesPassedNullValue()
    {
        // passed null value should override preconfigured value "invalid"
        $this->context->addViolation('Error', array('foo' => 'bar'), null);
        $this->context->addViolation('Error', array('foo' => 'bar'), null, 1);

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar',
                null
            ),
            new ConstraintViolation(
                'Error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar',
                null,
                1
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationAtPath()
    {
        // override preconfigured property path
        $this->context->addViolationAtPath('bar.baz', 'Error', array('foo' => 'bar'), 'invalid');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Error',
                array('foo' => 'bar'),
                'Root',
                'bar.baz',
                'invalid'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationAtPathUsesPreconfiguredValueIfNotPassed()
    {
        $this->context->addViolationAtPath('bar.baz', 'Error');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Error',
                array(),
                'Root',
                'bar.baz',
                'currentValue'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationAtPathUsesPassedNullValue()
    {
        // passed null value should override preconfigured value "invalid"
        $this->context->addViolationAtPath('bar.baz', 'Error', array('foo' => 'bar'), null);
        $this->context->addViolationAtPath('bar.baz', 'Error', array('foo' => 'bar'), null, 1);

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Error',
                array('foo' => 'bar'),
                'Root',
                'bar.baz',
                null
            ),
            new ConstraintViolation(
                'Error',
                array('foo' => 'bar'),
                'Root',
                'bar.baz',
                null,
                1
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationAtSubPath()
    {
        // override preconfigured property path
        $this->context->addViolationAtSubPath('bam.baz', 'Error', array('foo' => 'bar'), 'invalid');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar.bam.baz',
                'invalid'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationAtSubPathUsesPreconfiguredValueIfNotPassed()
    {
        $this->context->addViolationAtSubPath('bam.baz', 'Error');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Error',
                array(),
                'Root',
                'foo.bar.bam.baz',
                'currentValue'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationAtSubPathUsesPassedNullValue()
    {
        // passed null value should override preconfigured value "invalid"
        $this->context->addViolationAtSubPath('bam.baz', 'Error', array('foo' => 'bar'), null);
        $this->context->addViolationAtSubPath('bam.baz', 'Error', array('foo' => 'bar'), null, 1);

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar.bam.baz',
                null
            ),
            new ConstraintViolation(
                'Error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar.bam.baz',
                null,
                1
            ),
        )), $this->context->getViolations());
    }

    public function testGetPropertyPath()
    {
        $this->assertEquals('foo.bar', $this->context->getPropertyPath());
    }

    public function testGetPropertyPathWithIndexPath()
    {
        $this->assertEquals('foo.bar[bam]', $this->context->getPropertyPath('[bam]'));
    }

    public function testGetPropertyPathWithEmptyPath()
    {
        $this->assertEquals('foo.bar', $this->context->getPropertyPath(''));
    }

    public function testGetPropertyPathWithEmptyCurrentPropertyPath()
    {
        $this->context = new ExecutionContext($this->globalContext, 'currentValue', '', 'Group', 'ClassName', 'propertyName');

        $this->assertEquals('bam.baz', $this->context->getPropertyPath('bam.baz'));
    }
}
