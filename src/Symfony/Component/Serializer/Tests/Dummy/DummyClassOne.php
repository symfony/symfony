<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Dummy;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Attribute\SerializedName;

class DummyClassOne
{
    #[MaxDepth(1)]
    #[Groups(['book:read', 'book:write'])]
    #[SerializedName('identifier')]
    #[Ignore]
    #[Context(
        normalizationContext: ['groups' => ['book:read']],
        denormalizationContext: ['groups' => ['book:write']],
    )]
    public string $code;

    public string $name;
}
