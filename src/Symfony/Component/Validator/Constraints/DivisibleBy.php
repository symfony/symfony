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
 * @author Colin O'Dell <colinodell@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class DivisibleBy extends AbstractComparison
{
    public const NOT_DIVISIBLE_BY = '6d99d6c3-1464-4ccf-bdc7-14d083cf455c';

    protected const ERROR_NAMES = [
        self::NOT_DIVISIBLE_BY => 'NOT_DIVISIBLE_BY',
    ];

    public string $message = 'This value should be a multiple of {{ compared_value }}.';
}
