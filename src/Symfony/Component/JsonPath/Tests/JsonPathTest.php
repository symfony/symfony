<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\JsonPath;

class JsonPathTest extends TestCase
{
    public function testBuildPath()
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->index(0)
            ->key('address');

        $this->assertSame('$.users[0].address', (string) $path);
        $this->assertSame('$.users[0].address..city', (string) $path->deepScan()->key('city'));
    }

    public function testBuildWithFilter()
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->filter('@.age > 18');

        $this->assertSame('$.users[?(@.age > 18)]', (string) $path);
    }

    public function testAll()
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->all();

        $this->assertSame('$.users[*]', (string) $path);
    }

    public function testFirst()
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->first();

        $this->assertSame('$.users[0]', (string) $path);
    }

    public function testLast()
    {
        $path = new JsonPath();
        $path = $path->key('users')
            ->last();

        $this->assertSame('$.users[-1]', (string) $path);
    }
}
