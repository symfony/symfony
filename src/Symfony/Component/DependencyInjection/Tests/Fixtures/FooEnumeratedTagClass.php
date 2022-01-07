<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooEnumeratedTagClass
{
    public static function getDefaultFooName()
    {
        return FooBackedEnum::FOO;
    }

    public static function getPriority(): int
    {
        // Should be more than BarTagClass. More because this class is after
        // BarTagClass (order by name). So we want to ensure it will be before it
        return 20;
    }
}
