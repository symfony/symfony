<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(D::class)]
class InvalidConfiguration
{
    public function __construct(#[Map('baz')] public readonly string $foo, #[Map(transform: 'wrongMethod')] public readonly string $bar)
    {
    }
}
