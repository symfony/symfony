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

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Tag::class)]
#[Map(source: Named::class)]
class AmbiguousTagDto
{
    public string $label = '';
}
