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
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Cas Leentfaar <info@casleentfaar.com>
 */
class AnyValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Any) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\Any');
        }

        if (null === $value) {
            return;
        }

        $context = $this->context;
        $group = $context->getGroup();

        if (!$context instanceof ExecutionContext) {
            throw new \LogicException('Don\'t know how to deal with this when we need to create separate contexts later');
        }

        foreach ($constraint->constraints as $subConstraint) {
            $subContext = new ExecutionContext(
                $context->getValidator(),
                $context->getRoot(),
                $context->getTranslator(),
                $context->getTranslationDomain()
            );
            if ($context instanceof ExecutionContextInterface) {
                $subContext->getValidator()->validate($value, $subConstraint);
            } else {
                // 2.4 API
                $subContext->validateValue($value, $subConstraint);
            }
            $violations = $subContext->getViolations();
            if ($violations && $violations->count() === 0) {
                return;
            }
        }

        if ($this->context instanceof ExecutionContextInterface) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->setInvalidValue($value)
                ->addViolation();
        } else {
            // 2.4 API
            $this->context->addViolation(
                $constraint->message,
                array('{{ value }}' => $value),
                $value
            );
        }
    }
}
