<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\KeyLoader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;

class InMemoryKeyLoaderTest extends TestCase
{
    public function testReturnsExactBytes()
    {
        $loader = new InMemoryKeyLoader(['app' => str_repeat("\xAA", 32)]);

        $this->assertSame(str_repeat("\xAA", 32), $loader->load('app'));
    }

    public function testThrowsKeyNotFoundForUnknownKey()
    {
        $loader = new InMemoryKeyLoader([]);

        $this->expectException(KeyNotFoundException::class);
        $loader->load('missing');
    }
}
