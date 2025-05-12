<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\HydrateObject;

use Symfony\Component\ObjectMapper\Attribute\Map;

class SourceOnly
{
    public function __construct(#[Map(source: 'name')] public string $mappedName)
    {
    }
}
