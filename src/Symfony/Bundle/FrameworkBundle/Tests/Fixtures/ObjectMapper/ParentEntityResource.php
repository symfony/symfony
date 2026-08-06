<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\ObjectMapper;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: ParentEntity::class)]
final class ParentEntityResource
{
    public function __construct(
        public string $name = '',
        public ?NestedEntityResource $nested = null,
    ) {
    }
}
