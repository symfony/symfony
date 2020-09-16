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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 */
class Hostname extends Constraint
{
    const INVALID_HOSTNAME_ERROR = '7057ffdb-0af4-4f7e-bd5e-e9acfa6d7a2d';

    protected static $errorNames = [
        self::INVALID_HOSTNAME_ERROR => 'INVALID_HOSTNAME_ERROR',
    ];

    public $message = 'This value is not a valid hostname.';
    public $requireTld = true;
}
