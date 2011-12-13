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

use Symfony\Component\Validator\Constraint;

/**
 * Specifies an object able to return the correct ConstraintValidatorInterface
 * instance given a Constrain object.
 */
interface ConstraintValidatorFactoryInterface
{
    /**
     * Given a Constrain, this returns the ConstraintValidatorInterface
     * object that should be used to verify its validity.
     *
     * @param Constraint $constraint The source constraint
     *
     * @return ConstraintValidatorInterface
     */
    function getInstance(Constraint $constraint);
}
