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
use Symfony\Component\Validator\Exception\InvalidArgumentException;

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
        // Every constraint has a validatedBy method which determines the validator
        $validatorClass = $constraint->getConstraint()->validatedBy();

        // Expression needs dependencies in it's constructor
        if ($validatorClass === Expression::class) {
            throw new InvalidArgumentException(
                sprintf('%s is not supported by the %s', Expression::class, this::class)
            );
        }

        // Instantiate the validator class and init it providing the same context
        $validator = new $validatorClass();
        $validator->initialize($this->context);
        $validator->validate($value, $constraint->getConstraint());
    }

}
