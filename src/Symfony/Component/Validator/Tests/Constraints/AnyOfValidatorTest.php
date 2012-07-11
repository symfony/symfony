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
	protected $walker;
	protected $context;
	protected $violations;
	protected $validator;
	protected $constraint;

	protected function setUp()
	{
		$this->violations = $this->getMock('Symfony\Component\Validator\ConstraintViolationList', array(), array(), '', false);
		$this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
		$this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);

		$this->validator = new AnyOfValidator();
		$this->validator->initialize($this->context);
		$this->constraint = new AnyOf(
			array('constraints' => array(
				new MinLength(5),
				new Null
			))
		);

		$this->context->expects($this->any())
			->method('getGraphWalker')
			->will($this->returnValue($this->walker));
		$this->context->expects($this->any())
			->method('getViolations')
			->will($this->returnValue($this->violations));
		$this->violations->expects($this->any())
			->method('has')
			->will($this->returnValue(false));

		for ($i = 0; $i < count($this->constraint->constraints); $i ++) {
			$this->walker->expects($this->at($i))
				->method('walkConstraint');
		}

		$this->violations->expects($this->once())
			->method('getIterator')
			->will($this->returnValue(new \ArrayIterator(array(1,2,3))));
	}

	protected function tearDown()
	{
		$this->walker = null;
		$this->context = null;
		$this->validator = null;
		$this->violations = null;
		$this->constraint = null;
	}

	public function testNoOneIsValid()
	{
		$value = '123';

		$constraintsClasses =
			get_class($this->constraint->constraints[0]) . ', ' .
			get_class($this->constraint->constraints[1]);

		$this->context->expects($this->once())
			->method('addViolation')
			->with(
				$this->constraint->message,
				array('{{ value }}' => $value, '{{ constraints }}' => $constraintsClasses)
			);

		for ($i = 0; $i < 3; $i ++) {
			$this->violations->expects($this->at($i))
				->method('count')
				->will($this->returnValue($i));
		}

		$this->validator->validate($value, $this->constraint);
	}

	public function testOneIsValid()
	{
		$this->context->expects($this->never())
			->method('addViolation');

		$this->validator->validate(null, $this->constraint);
	}

	public function testSecondOneIsValid()
	{
		$this->context->expects($this->never())
			->method('addViolation');

		$this->validator->validate('123456', $this->constraint);
	}
}
