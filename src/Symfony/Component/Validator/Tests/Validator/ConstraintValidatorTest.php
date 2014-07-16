<?php
/**
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potiencier (fabien@symfony.com)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 *
 */

namespace Symfony\Component\Validator\Tests\Validator;

use Symfony\Component\Validator\Tests\Fixtures\ConstraintAValidator;

class ConstraintValidatorTest extends \PHPUnit_Framework_TestCase
{
    public function testConstraintValidatorSinceSymfony25()
    {
        $context = $this->getMock('Symfony\Component\Validator\Context\ExecutionContextInterface');
        $constraintvalidator = new ConstraintAValidator();
        $constraintvalidator->initialize($context);
        $this->assertAttributeSame($context, 'context', $constraintvalidator);
    }

    public function testConstraintValidatorUntilSymfony24()
    {
        $context = $this->getMock('Symfony\Component\Validator\ExecutionContextInterface');
        $constraintvalidator = new ConstraintAValidator();
        $constraintvalidator->initialize($context);
        $this->assertAttributeSame($context, 'context', $constraintvalidator);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidConstraintValidator()
    {
        $constraintvalidator = new ConstraintAValidator();
        $constraintvalidator->initialize(null);
    }
}