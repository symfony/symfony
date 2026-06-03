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

class MultiResettableService
{
    public static $resetFirstCounter = 0;
    public static $resetSecondCounter = 0;

    public function resetFirst()
    {
        ++self::$resetFirstCounter;
    }

    public function resetSecond()
    {
        ++self::$resetSecondCounter;
    }
}
