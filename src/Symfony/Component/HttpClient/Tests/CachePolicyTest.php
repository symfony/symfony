<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CachePolicy;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;

class CachePolicyTest extends TestCase
{
    public function testTagsMustNotHoldReservedCharacters()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contains one of the reserved characters');

        (new CachePolicy())->tag('foo{bar}');
    }

    public function testTagsMustBeNonEmptyStrings()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache tags must be non-empty strings, "int" given.');

        (new CachePolicy())->tag([42]);
    }
}
