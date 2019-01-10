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
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ConstraintValidatorInterface
{
    /**
     * Initializes the constraint validator.
     *
     * @param ExecutionContextInterface $context The current validation context
     */
    public function initialize(ExecutionContextInterface $context);

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @throws UnexpectedTypeException If the constraint or the value is incompatible (causes an unrecoverable error)
     */
    public function validate($value, Constraint $constraint);
}
