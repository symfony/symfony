<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\SubclassTargetWithTransform;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: ChildTarget::class, transform: [ChildTarget::class, 'transform'])]
class Source
{
    public string $name = 'test';
}
