<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

/**
 * Records whether unserialize() was allowed to build it.
 */
class WakingPayload
{
    public static bool $awoken = false;

    public static function reset(): void
    {
        self::$awoken = false;
    }

    public function __wakeup(): void
    {
        self::$awoken = true;
    }
}
