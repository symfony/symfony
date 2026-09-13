<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\InferredFromPropertyType;

class AmbiguousTagHolderWithPrivateProperty
{
    private ?AmbiguousTagDto $tag = null;

    public function getTag(): ?AmbiguousTagDto
    {
        return $this->tag;
    }
}
