<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Context\NullContext;

class NullContextTest extends TestCase
{
    public function testGetBasePath()
    {
        $nullContext = new NullContext();

        $this->assertEmpty($nullContext->getBasePath());
    }

    public function testIsSecure()
    {
        $nullContext = new NullContext();

        $this->assertFalse($nullContext->isSecure());
    }
}
