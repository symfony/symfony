<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMergeRecursion;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: Target::class)]
class Source
{
    public string $name;
    public ?Source $child = null;
}
