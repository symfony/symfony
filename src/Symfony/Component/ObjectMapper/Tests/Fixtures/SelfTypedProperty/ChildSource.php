<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\SelfTypedProperty;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: ChildTarget::class)]
class ChildSource
{
    public function __construct(
        public string $name,
        public ?RelatedSource $related = null,
        public ?RelatedSource $derivedFrom = null,
    ) {
    }
}
