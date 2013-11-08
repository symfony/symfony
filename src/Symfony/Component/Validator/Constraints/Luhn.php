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
 * Metadata for the LuhnValidator.
 *
 * @Annotation
 */
class Luhn extends Constraint
{
    const ERROR = 'e146c87e-3063-47ad-bde0-37cd2f5f117b';

    public $message = 'Invalid card number.';
}
