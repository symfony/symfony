<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Fixtures;

use Symphony\Component\Validator\Constraint;
use Symphony\Component\Validator\ConstraintValidator;
use Symphony\Component\Validator\Context\ExecutionContextInterface;

class ConstraintAValidator extends ConstraintValidator
{
    public static $passedContext;

    public function initialize(ExecutionContextInterface $context)
    {
        parent::initialize($context);

        self::$passedContext = $context;
    }

    public function validate($value, Constraint $constraint)
    {
        if ('VALID' != $value) {
            $this->context->addViolation('message', array('param' => 'value'));

            return;
        }
    }
}
