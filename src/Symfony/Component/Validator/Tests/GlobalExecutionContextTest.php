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

use Symfony\Component\Validator\ConstraintViolation;

use Symfony\Component\Validator\ConstraintViolationList;

use Symfony\Component\Validator\GlobalExecutionContext;

class GlobalExecutionContextTest extends \PHPUnit_Framework_TestCase
{
    protected $walker;
    protected $metadataFactory;
    protected $context;

    protected function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $this->context = new GlobalExecutionContext('Root', $this->walker, $this->metadataFactory);
    }

    protected function tearDown()
    {
        $this->walker = null;
        $this->metadataFactory = null;
        $this->context = null;
    }

    public function testInit()
    {
        $this->assertCount(0, $this->context->getViolations());
        $this->assertSame('Root', $this->context->getRoot());
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
        $violation = new ConstraintViolation('Error', array(), 'Root', 'foo.bar', 'invalid');

        $this->context->addViolation($violation);

        $this->assertEquals(new ConstraintViolationList(array($violation)), $this->context->getViolations());
    }
}
