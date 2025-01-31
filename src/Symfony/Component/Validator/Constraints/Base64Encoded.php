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
 * Ensures that the value is a valid Base64-encoded.
 *
 * @author Refat Alsakka <refatalsakka@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Base64Encoded extends Constraint
{
    public string $message = 'The string "{{ value }}" is not a valid Base64-encoded value.';
}
