<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Test;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
trait ForwardCompatTestTrait
{
    private function doSetUp(): void
    {
    }

    private function doTearDown(): void
    {
    }

    protected function setUp(): void
    {
        $this->doSetUp();
    }

    protected function tearDown(): void
    {
        $this->doTearDown();
    }
}
