<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Constraints\ExpressionValidator;

/**
 * Default implementation of the ConstraintValidatorFactoryInterface.
 *
 * This enforces the convention that the validatedBy() method on any
 * Constraint will return the class name of the ConstraintValidator that
 * should validate the Constraint.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    protected array $validators;

    public function __construct(array $validators = [])
    {
        $this->validators = $validators;
    }

    public function getInstance(Constraint $constraint): ConstraintValidatorInterface
    {
        if ('validator.expression' === $name = $class = $constraint->validatedBy()) {
            $class = ExpressionValidator::class;
        }

        return $this->validators[$name] ??= new $class();
    }
}
