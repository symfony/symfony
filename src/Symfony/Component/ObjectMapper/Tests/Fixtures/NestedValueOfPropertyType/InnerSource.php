<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedValueOfPropertyType;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: InnerTarget::class)]
class InnerSource
{
    public string $value = 'inner-value';
}
