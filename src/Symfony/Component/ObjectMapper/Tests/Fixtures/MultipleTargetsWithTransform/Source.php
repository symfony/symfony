<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetsWithTransform;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: PlainTarget::class)]
#[Map(target: TransformedTarget::class, transform: [TransformedTarget::class, 'transform'])]
class Source
{
    public string $name = 'test';
}
