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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Colin O'Dell <colinodell@gmail.com>
 */
class DivisibleBy extends AbstractComparison
{
    const NOT_DIVISIBLE_BY = '6d99d6c3-1464-4ccf-bdc7-14d083cf455c';

    protected static $errorNames = [
        self::NOT_DIVISIBLE_BY => 'NOT_DIVISIBLE_BY',
    ];

    public $message = 'This value should be a multiple of {{ compared_value }}.';
}
