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

use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @method void validateInContext(mixed $value, Constraint $constraint, ExecutionContextInterface $context)
 */
interface ConstraintValidatorInterface
{
    /**
     * Initializes the constraint validator.
     *
     * @deprecated since Symfony 8.1, use "validateInContext()" instead
     */
    public function initialize(ExecutionContextInterface $context): void;

    /**
     * Checks if the passed value is valid.
     *
     * @deprecated since Symfony 8.1, use "validateInContext()" instead
     */
    public function validate(mixed $value, Constraint $constraint): void;
}
