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

/**
 * Checks if a password has been leaked in a data breach.
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class NotCompromisedPassword extends Constraint
{
    public const COMPROMISED_PASSWORD_ERROR = 'd9bcdbfe-a9d6-4bfa-a8ff-da5fd93e0f6d';

    protected static $errorNames = [self::COMPROMISED_PASSWORD_ERROR => 'COMPROMISED_PASSWORD_ERROR'];

    public $message = 'This password has been leaked in a data breach, it must not be used. Please use another password.';
    public $threshold = 1;
    public $skipOnError = false;
}
