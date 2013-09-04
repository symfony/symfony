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
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConditionValidator extends ConstraintValidator
{
    private $expressionLanguage;

    /**
     * {@inheritDoc}
     */
    public function validate($object, Constraint $constraint)
    {
        if (null === $object) {
            return;
        }

        if (!$this->getExpressionLanguage()->evaluate($constraint->condition, array('this' => $object))) {
            $this->context->addViolation($constraint->message);
        }
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage();
        }

        return $this->expressionLanguage;
    }
}
