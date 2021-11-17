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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ConditionallyValidator extends ConstraintValidator
{
    /** @var ExpressionLanguage|null */
    private $expressionLanguage;

    public function __construct(ExpressionLanguage $expressionLanguage = null)
    {
        $this->expressionLanguage = $expressionLanguage;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Conditionally) {
            throw new UnexpectedTypeException($constraint, Conditionally::class);
        }

        $variables = [
            'value' => $value,
            'this' => $this->context->getObject(),
        ];

        if (!$this->getExpressionLanguage()->evaluate($constraint->condition, $variables)) {
            return;
        }

        $context = $this->context;
        $validator = $context->getValidator()->inContext($context);

        foreach ($constraint->constraints as $conditionalConstraint) {
            $validator->validate($value, $conditionalConstraint);
        }
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        if (null === $this->expressionLanguage) {
            $this->expressionLanguage = new ExpressionLanguage();
        }

        return $this->expressionLanguage;
    }
}
