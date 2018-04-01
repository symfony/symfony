<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator;

/**
 * Specifies an object able to return the correct ConstraintValidatorInterface
 * instance given a Constraint object.
 */
interface ConstraintValidatorFactoryInterface
{
    /**
     * Given a Constraint, this returns the ConstraintValidatorInterface
     * object that should be used to verify its validity.
     *
     * @return ConstraintValidatorInterface
     */
    public function getInstance(Constraint $constraint);
}
