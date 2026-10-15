<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Runner;

use Symfony\Component\Runtime\Tests\FrankenPhpWorkerRunnerTest;

function gc_status(): array
{
    return FrankenPhpWorkerRunnerTest::$gcStatus ?? \gc_status();
}

function gc_collect_cycles(): int
{
    ++FrankenPhpWorkerRunnerTest::$gcCollections;

    return \gc_collect_cycles();
}
