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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validator;

/**
 * @author Michael Hindley <mikael.chojnacki@gmail.com>
 */
class L18nValidator extends ConstraintValidator
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        // Check that the locale is matching, else ignore this constraint
        if ($this->requestStack->getCurrentRequest()->getLocale() !== $constraint->getLocale()) {
            return;
        }

        if (!$constraint instanceof L18n) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\L18n');
        }

        if (null === $value) {
            return;
        }

        if (!is_array($value) && !$value instanceof \Traversable) {
            throw new UnexpectedTypeException($value, 'array or Traversable');
        }

        $context = $this->context;

        if ($context instanceof ExecutionContextInterface) {
            $validator = $context->getValidator()->inContext($context);

            foreach ($value as $key => $element) {
                $validator->atPath('[' . $key . ']')->validate($element, $constraint->constraints);
            }
        } else {
            // 2.4 API
            foreach ($value as $key => $element) {
                $context->validateValue($element, $constraint->constraints, '[' . $key . ']');
            }
        }
    }
}

