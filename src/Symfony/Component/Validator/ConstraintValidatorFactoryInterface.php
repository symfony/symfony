<?php

namespace Symfony\Component\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
     * @return ConstraintValidatorInterface
     */
    function getInstance(Constraint $constraint);
}
