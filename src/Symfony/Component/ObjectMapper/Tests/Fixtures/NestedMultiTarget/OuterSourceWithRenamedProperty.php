<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMultiTarget;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: OuterTargetWithRenamedProperty::class)]
class OuterSourceWithRenamedProperty
{
    #[Map(target: 'nested')]
    public InnerSource $inner;

    public function __construct()
    {
        $this->inner = new InnerSource();
    }
}
