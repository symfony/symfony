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

#[Map(ScalarToEnumTarget::class)]
class ScalarToEnumSource
{
    public string $status;
    public int $priority;
}
