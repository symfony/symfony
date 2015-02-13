<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints\Deprecated;

trigger_error('Constants STRICT_PATTERN, LOOSE_PATTERN and STRICT_UUID_LENGTH in class Symfony\Component\Validator\Constraints\UuidValidator are deprecated since version 2.6 and will be removed in 3.0.', E_USER_DEPRECATED);

/**
 * @deprecated since version 2.7, to be removed in 3.0.
 * @internal
 */
final class UuidValidator
{
    const STRICT_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[%s][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i';
    const LOOSE_PATTERN = '/^[a-f0-9]{4}(?:-?[a-f0-9]{4}){7}$/i';
    const STRICT_UUID_LENGTH = 36;

    private function __construct()
    {
        
    }
}
