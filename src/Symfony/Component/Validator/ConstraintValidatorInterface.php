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

interface ConstraintValidatorInterface
{
    public function initialize(ValidationContext $context);

    public function isValid($value, Constraint $constraint);

    public function getMessageTemplate();

    public function getMessageParameters();
}
