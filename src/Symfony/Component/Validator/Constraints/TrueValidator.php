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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class TrueValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if (null === $value) {
            return true;
        }

        if (true === $value || 1 === $value || '1' === $value) {
            return true;
        }

        $this->setMessage($constraint->message);

        return false;
    }
}
