<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMergeRead;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: Target::class)]
class HookSource
{
    public ?Address $address = null {
        get => $this->address ??= new Address();
    }
}
