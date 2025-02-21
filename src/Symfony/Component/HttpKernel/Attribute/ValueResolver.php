<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Symfony\Component\ArgumentResolver\Attribute\ValueResolver as BaseValueResolver;

/**
 * Defines which value resolver should be used for a given parameter.
 *
 * @deprecated since Symfony 7.3, use {@see BaseValueResolver} instead
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class ValueResolver extends BaseValueResolver
{
}
