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

class ClosableService
{
    public static int $closed = 0;

    public function close(): void
    {
        ++self::$closed;
    }

    public function fail(): void
    {
        throw new \RuntimeException('Cannot close.');
    }
}
