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

class EnumFormatter
{
    public static function label(StringStatus $status): string
    {
        return ucfirst($status->value);
    }

    public static function length(StringStatus $status): int
    {
        return \strlen($status->value);
    }

    public static function name(PureColor $color): string
    {
        return $color->name;
    }
}
