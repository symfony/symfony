<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator;

use Symfony\Component\Validator\ExecutionContext;

class ExecutionContextTest extends \PHPUnit_Framework_TestCase
{
    protected $walker;
    protected $metadataFactory;
    protected $context;

    protected function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $this->context = new ExecutionContext('Root', $this->walker, $this->metadataFactory);
    }

    public function testClone()
    {
        $clone = clone $this->context;

        $this->assertNotSame($this->context, $clone);
    }

    public function testAddViolation()
    {
        $this->assertEquals(0, count($this->context->getViolations()));
        $this->context->addViolation('', array(), '');

        $this->assertEquals(1, count($this->context->getViolations()));
    }

    public function testGetViolations()
    {
        $this->context->addViolation('', array(), '');

        $this->assertEquals(1, count($this->context->getViolations()));
        $this->assertInstanceOf('Symfony\Component\Validator\ConstraintViolationList', $this->context->getViolations());
    }

    public function testGetRoot()
    {
        $this->assertEquals('Root', $this->context->getRoot());
    }

    public function testSetGetPropertyPath()
    {
        $this->context->setPropertyPath('property_path');

        $this->assertEquals('property_path', $this->context->getPropertyPath());
    }

    public function testSetGetCurrentClass()
    {
        $this->context->setCurrentClass('current_class');

        $this->assertEquals('current_class', $this->context->getCurrentClass());
    }

    public function testSetGetCurrentProperty()
    {
        $this->context->setCurrentProperty('current_property');

        $this->assertEquals('current_property', $this->context->getCurrentProperty());
    }

    public function testSetGetGroup()
    {
        $this->context->setGroup('group');

        $this->assertEquals('group', $this->context->getGroup());
    }

    public function testGetGraphWalker()
    {
        $this->assertSame($this->walker, $this->context->getGraphWalker());
        $this->assertInstanceOf(
            'Symfony\Component\Validator\GraphWalker',
            $this->context->getGraphWalker()
        );
    }

    public function testGetMetadataFactory()
    {
        $this->assertSame($this->metadataFactory, $this->context->getMetadataFactory());
        $this->assertInstanceOf(
            'Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface',
            $this->context->getMetadataFactory()
        );
    }
}