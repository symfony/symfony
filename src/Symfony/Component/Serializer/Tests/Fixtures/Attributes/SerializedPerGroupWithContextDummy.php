<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class SerializedPerGroupWithContextDummy
{
    #[Groups(['a', 'b'])]
    #[Context(normalizationContext: ['groups' => ['b']], denormalizationContext: ['groups' => ['b']])]
    #[SerializedName('inA', 'a')]
    #[SerializedName('inB', 'b')]
    public $name;

    #[Groups(['a', 'b'])]
    #[Context(normalizationContext: ['groups' => ['b']], denormalizationContext: ['groups' => ['b']])]
    #[SerializedPath('[in][a]', 'a')]
    #[SerializedPath('[in][b]', 'b')]
    public $path;
}
