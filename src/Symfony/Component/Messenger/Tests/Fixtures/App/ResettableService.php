<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures\App;

use Symfony\Contracts\Service\ResetInterface;

final class ResettableService implements ResetInterface
{
    public static int $resets = 0;
    public static bool $failing = false;

    public function reset(): void
    {
        ++self::$resets;

        if (self::$failing) {
            throw new \RuntimeException('Reset failed.');
        }
    }
}
