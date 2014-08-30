<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PhoneNumberValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null !== $value) {
            $phoneNumber = PhoneNumberUtil::getInstance()->parse($value, $constraint->region);
            if (!PhoneNumberUtil::getInstance()->isValidNumber($phoneNumber)) {
                $this->context->addViolation($constraint->message);
            }
        }
    }
}
