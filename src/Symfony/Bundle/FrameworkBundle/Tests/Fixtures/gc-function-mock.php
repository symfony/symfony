<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\GcFunctionMock;

function gc_status(): array
{
    return GcFunctionMock::$status ?? \gc_status();
}

function gc_collect_cycles(): int
{
    ++GcFunctionMock::$collections;

    return \gc_collect_cycles();
}

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\GcFunctionMock;

function gc_status(): array
{
    return GcFunctionMock::$status ?? \gc_status();
}

function gc_collect_cycles(): int
{
    ++GcFunctionMock::$collections;

    return \gc_collect_cycles();
}
