<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests;

use Symfony\Component\String\AbstractString;
use Symfony\Component\String\ByteString;

class ByteStringTest extends AbstractAsciiTestCase
{
    protected static function createFromString(string $string): AbstractString
    {
        return new ByteString($string);
    }

    public static function provideLength(): array
    {
        return array_merge(
            parent::provideLength(),
            [
                [2, 'ä'],
            ]
        );
    }
}
