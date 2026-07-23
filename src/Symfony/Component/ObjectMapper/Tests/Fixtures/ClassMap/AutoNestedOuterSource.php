<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: AutoNestedFlatTarget::class)]
final class AutoNestedOuterSource
{
    public string $outer = 'from outer';
    public AutoNestedInnerSource $child;

    public function __construct()
    {
        $this->child = new AutoNestedInnerSource();
    }
}
