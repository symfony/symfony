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

/**
 * Validates that a value is not equal to another value.
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class NotEqualTo extends AbstractComparison
{
    public const IS_EQUAL_ERROR = 'aa2e33da-25c8-4d76-8c6c-812f02ea89dd';

    protected const ERROR_NAMES = [
        self::IS_EQUAL_ERROR => 'IS_EQUAL_ERROR',
    ];

    public string $message = 'This value should not be equal to {{ compared_value }}.';
}
