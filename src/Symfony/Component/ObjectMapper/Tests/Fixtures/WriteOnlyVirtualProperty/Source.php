<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\WriteOnlyVirtualProperty;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: SameNameTarget::class)]
class Source
{
    private string $backing = '';

    public ?string $nickname {
        set {
            $this->backing = $value ?? '';
        }
    }
}
