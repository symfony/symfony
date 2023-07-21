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
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class PositiveOrZero extends GreaterThanOrEqual
{
    use ZeroComparisonConstraintTrait;

    public string $message = 'This value should be either positive or zero.';
}
