<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: Tag::class)]
final class TagDto
{
    #[Map]
    public string $name = 'updated name';
}
