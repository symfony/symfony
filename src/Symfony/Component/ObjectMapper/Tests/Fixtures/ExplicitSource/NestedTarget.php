<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitSource;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: NestedSource::class)]
class NestedTarget
{
    public function __construct(#[Map(source: 'reason.description')] public string $reason)
    {
    }
}
