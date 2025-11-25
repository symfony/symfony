<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\EnumMapping;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapEnum;

#[Map(ExplicitEnumTransformTarget::class)]
class ExplicitEnumTransformSource
{
    #[Map(transform: new MapEnum('string'))]
    public StringStatus $status;
}
