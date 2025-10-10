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

#[Map(target: ObjectMapped::class)]
final class ObjectToBeMapped
{
    #[Map(transform: TransformCallable::class)]
    public string $a = 'nottransformed';
}
