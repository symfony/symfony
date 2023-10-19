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

use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\SerializedName;

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
