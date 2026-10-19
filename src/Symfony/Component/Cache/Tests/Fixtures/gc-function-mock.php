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

use Symfony\Component\Cache\Tests\CacheBundleTest;

// the tests of several components declare this function, the first one loaded is used when they run together
if (!\function_exists(__NAMESPACE__.'\gc_collect_cycles')) {
    function gc_collect_cycles(): int
    {
        return CacheBundleTest::$collectCycles ? \gc_collect_cycles() : 0;
    }
}
