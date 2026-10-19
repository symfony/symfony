<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Kernel;

use Symfony\Component\Lock\Tests\LockBundleTest;

function gc_collect_cycles(): int
{
    return LockBundleTest::$collectCycles ? \gc_collect_cycles() : 0;
}
