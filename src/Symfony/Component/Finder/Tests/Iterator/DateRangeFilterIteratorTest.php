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

use Symfony\Component\Finder\Iterator\DateRangeFilterIterator;
use Symfony\Component\Finder\Comparator\DateComparator;

class DateRangeFilterIteratorTest extends RealIteratorTestCase
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$files[] = self::toAbsolute('atime.php');
    }

    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($size, $expected)
    {
        $files = self::$files;
        $files[] = self::toAbsolute('doesnotexist');
        $inner = new Iterator($files);

        $iterator = new DateRangeFilterIterator($inner, $size);

        $this->assertIterator($expected, $iterator);
    }

    public function getAcceptData()
    {
        $since20YearsAgo = array(
            '.git',
            'test.py',
            'foo',
            'foo/bar.tmp',
            'test.php',
            'toto',
            'toto/.git',
            '.bar',
            '.foo',
            '.foo/.bar',
            'foo bar',
            '.foo/bar',
            'atime.php',
        );

        $since2MonthsAgo = array(
            '.git',
            'test.py',
            'foo',
            'toto',
            'toto/.git',
            '.bar',
            '.foo',
            '.foo/.bar',
            'foo bar',
            '.foo/bar',
        );

        $accessedSince2MonthsAgo = array(
            '.git',
            'test.py',
            'foo',
            'toto',
            'toto/.git',
            '.bar',
            '.foo',
            '.foo/.bar',
            'foo bar',
            '.foo/bar',
            'atime.php',
        );

        $untilLastMonth = array(
            'atime.php',
            'foo/bar.tmp',
            'test.php',
        );

        $accessedUntilLastMonth = array(
            'foo/bar.tmp',
            'test.php',
        );

        return array(
            array(array(new DateComparator('since 20 years ago', DateComparator::TIME_TYPE_ACCESSED)), $this->toAbsolute($since20YearsAgo)),
            array(array(new DateComparator('since 20 years ago', DateComparator::TIME_TYPE_CHANGED)), $this->toAbsolute($since20YearsAgo)),
            array(array(new DateComparator('since 20 years ago', DateComparator::TIME_TYPE_MODIFIED)), $this->toAbsolute($since20YearsAgo)),
            array(array(new DateComparator('since 2 months ago', DateComparator::TIME_TYPE_ACCESSED)), $this->toAbsolute($accessedSince2MonthsAgo)),
            array(array(new DateComparator('since 2 months ago', DateComparator::TIME_TYPE_CHANGED)), $this->toAbsolute($since20YearsAgo)),
            array(array(new DateComparator('since 2 months ago', DateComparator::TIME_TYPE_MODIFIED)), $this->toAbsolute($since2MonthsAgo)),
            array(array(new DateComparator('until last month', DateComparator::TIME_TYPE_ACCESSED)), $this->toAbsolute($accessedUntilLastMonth)),
            array(array(new DateComparator('until last month', DateComparator::TIME_TYPE_CHANGED)), $this->toAbsolute(array())),
            array(array(new DateComparator('until last month', DateComparator::TIME_TYPE_MODIFIED)), $this->toAbsolute($untilLastMonth)),
        );
    }
}
