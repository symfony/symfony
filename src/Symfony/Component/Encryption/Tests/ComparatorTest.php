<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Comparator;

final class ComparatorTest extends TestCase
{
    public function testEqualStringsMatch()
    {
        self::assertTrue(Comparator::equals('correct-horse', 'correct-horse'));
    }

    public function testDifferentStringsDoNotMatch()
    {
        self::assertFalse(Comparator::equals('correct-horse', 'battery-staple'));
    }

    public function testDifferentLengthsDoNotMatch()
    {
        self::assertFalse(Comparator::equals('short', 'considerably-longer'));
    }

    public function testEmptyStringsMatch()
    {
        self::assertTrue(Comparator::equals('', ''));
    }
}
