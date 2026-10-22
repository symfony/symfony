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

use Symfony\Component\Serializer\Attribute\MaxDepth;

class MaxDepthZeroDummy
{
    public function __construct(
        public string $name,
        #[MaxDepth(0)]
        public ?self $child = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getChild(): ?self
    {
        return $this->child;
    }
}
