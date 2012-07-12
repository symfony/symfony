<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\AnyOf;
use Symfony\Component\Validator\Constraints\MinLength;
use Symfony\Component\Validator\Constraints\Null;
use Symfony\Component\Validator\Constraints\AnyOfValidator;

class AnyOfValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $violations;
    protected $validator;
    protected $constraint;

    protected function setUp()
    {
        $this->violations = $this->getMock('Symfony\Component\Validator\ConstraintViolationList', array(), array(), '', false);
        $context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);

        $this->validator = new AnyOfValidator();
        $this->validator->initialize($context);
        $this->constraint = new AnyOf(
            array('constraints' => array(
                new MinLength(5),
                new Null
            ))
        );

        $context->expects($this->any())
            ->method('getGraphWalker')
            ->will($this->returnValue($walker));
        $context->expects($this->any())
            ->method('getViolations')
            ->will($this->returnValue($this->violations));

        for ($i = 0; $i < count($this->constraint->constraints); $i ++) {
            $walker->expects($this->at($i))->method('walkConstraint');
        }
    }

    protected function tearDown()
    {
        $this->validator = null;
        $this->violations = null;
        $this->constraint = null;
    }

    public function testNoOneIsValid()
    {
        $value = '123';

        $this->violations->expects($this->never())->method('remove');

        $this->violations->expects($this->at(0))
            ->method('count')
            ->will($this->returnValue(0));

        for ($i = 0; $i < 3; $i ++) {
            $this->violations->expects($this->at($i + 1))
                ->method('count')
                ->will($this->returnValue($i));
        }

        $this->violations->expects($this->once())
            ->method('getIterator')
            ->will($this->returnValue(new \ArrayIterator(array())));

        $this->validator->validate($value, $this->constraint);
    }

    public function testOneIsValid()
    {
        $this->expectRemovalOfViolations();

        $this->violations->expects($this->at(2))
            ->method('count')
            ->will($this->returnValue(1));

        $this->violations->expects($this->at(3))
            ->method('count')
            ->will($this->returnValue(1));

        // actually value `null` here doesn't make sense since everything is mocked
        $this->validator->validate(null, $this->constraint);
    }

    public function testSecondOneIsValid()
    {
        $this->expectRemovalOfViolations();

        $this->violations->expects($this->at(2))
            ->method('count')
            ->will($this->returnValue(0));

        $this->violations->expects($this->at(3))
            ->method('count')
            ->will($this->returnValue(1));

        $this->validator->validate('123456', $this->constraint);
    }

    public function testWithExistingViolationsInAList()
    {
        $this->expectRemovalOfViolations(3, array_fill(0, 3, ''));

        $this->violations->expects($this->at(2))
            ->method('count')
            ->will($this->returnValue(4));

        $this->violations->expects($this->at(3))
            ->method('count')
            ->will($this->returnValue(4));

        $this->validator->validate('123456', $this->constraint);
    }

    protected function expectRemovalOfViolations($startingAt = 0, $initialArray = array())
    {
        $this->violations->expects($this->at(0))
            ->method('getIterator')
            ->will($this->returnValue(new \ArrayIterator($initialArray)));

        $this->violations->expects($this->at(1))
            ->method('count')
            ->will($this->returnValue($startingAt));

        $this->violations->expects($this->at(4))
            ->method('getIterator')
            ->will($this->returnValue(new \ArrayIterator(array_fill(0, $startingAt + 2, ''))));

        for ($i = 0; $i < 2; $i ++) {
            $this->violations->expects($this->at(5 + $i))
                ->method('remove')
                ->with($startingAt + $i);
        }
    }
}
