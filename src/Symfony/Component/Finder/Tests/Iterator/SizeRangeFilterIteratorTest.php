<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

use Symfony\Component\Finder\Comparator\NumberComparator;
use Symfony\Component\Finder\Iterator\SizeRangeFilterIterator;

class SizeRangeFilterIteratorTest extends RealIteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($size, $expected)
    {
        $inner = new InnerSizeIterator(self::$files);

        $iterator = new SizeRangeFilterIterator($inner, $size);

        $this->assertIterator($expected, $iterator);
    }

    public static function getAcceptData()
    {
        $lessThan1KGreaterThan05K = [
            '.foo',
            '.git',
            'foo',
            'qux',
            'test.php',
            'toto',
            'toto/.git',
        ];

        return [
            [[new NumberComparator('< 1K'), new NumberComparator('> 0.5K')], self::toAbsolute($lessThan1KGreaterThan05K)],
        ];
    }
}

class InnerSizeIterator extends \ArrayIterator
{
    public function current(): \SplFileInfo
    {
        return new \SplFileInfo(parent::current());
    }

    public function getFilename(): string
    {
        return parent::current();
    }

    public function isFile(): bool
    {
        return $this->current()->isFile();
    }

    public function getSize(): int
    {
        return $this->current()->getSize();
    }
}
