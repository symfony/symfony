<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\SelfReferencing;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: CategoryDto::class)]
class Category
{
    public ?Category $parent = null;

    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}
