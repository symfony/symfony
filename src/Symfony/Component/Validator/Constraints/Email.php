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
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Email extends Constraint
{
    const ERROR = '38b1dcdc-501a-43c1-98e1-4ab0971ca1b2';

    public $message = 'This value is not a valid email address.';
    public $checkMX = false;
    public $checkHost = false;
}
