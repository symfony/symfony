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

final class CategoryDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }
}
