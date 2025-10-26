<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DepthProxy;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Attribute\TransformAllProperties;
use Symfony\Component\ObjectMapper\Transform\UninitializeProxy;

#[Map(target: UserDto::class)]
#[TransformAllProperties(transform: new UninitializeProxy(maxDepth: 1))]
class User
{
    public function __construct(
        public string $name,
        public object $post,
    ) {
    }
}
