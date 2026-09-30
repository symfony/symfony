<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

#[\Attribute(\Attribute::TARGET_CLASS)]
#[ConstExprClosureDummy(static function (int $value): int {
    return 3 * $value;
})]
class ConstExprClosureDummy
{
    public function __construct(public \Closure $closure)
    {
    }
}
