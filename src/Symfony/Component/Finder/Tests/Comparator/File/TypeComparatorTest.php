<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Comparator\File;

use Symfony\Component\Finder\Comparator\File\TypeComparator;

class TypeComparatorTest extends \PHPUnit_Framework_TestCase
{
    public function testCompareTwoIdenticalFiles()
    {
        $a = new \SplFileInfo(__DIR__.'/../../Fixtures/lorem.txt');
        $b = new \SplFileInfo(__DIR__.'/../../Fixtures/lorem.txt');

        $this->assertSame(0, call_user_func_array(new TypeComparator(), array($a, $b)));
    }

    public function testCompareTwoFiles()
    {
        $a = new \SplFileInfo(__DIR__.'/../../Fixtures/lorem.txt');
        $b = new \SplFileInfo(__DIR__.'/../../Fixtures/ipsum.txt');

        $this->assertNotSame(0, call_user_func_array(new TypeComparator(), array($a, $b)));
    }

    public function testCompareTwoIdenticalDirectories()
    {
        $a = new \SplFileInfo(__DIR__.'/../../Fixtures/one');
        $b = new \SplFileInfo(__DIR__.'/../../Fixtures/one');

        $this->assertSame(0, call_user_func_array(new TypeComparator(), array($a, $b)));
    }

    public function testCompareTwoDirectories()
    {
        $a = new \SplFileInfo(__DIR__.'/../../Fixtures/one');
        $b = new \SplFileInfo(__DIR__.'/../../Fixtures/copy');

        $this->assertNotSame(0, call_user_func_array(new TypeComparator(), array($a, $b)));
    }

    public function testCompareFileAndDirectory()
    {
        $a = new \SplFileInfo(__DIR__.'/../../Fixtures/lorem.txt');
        $b = new \SplFileInfo(__DIR__.'/../../Fixtures/one');

        $this->assertSame(1, call_user_func_array(new TypeComparator(), array($a, $b)));
    }

    public function testCompareDirectoryAndFile()
    {
        $a = new \SplFileInfo(__DIR__.'/../../Fixtures/one');
        $b = new \SplFileInfo(__DIR__.'/../../Fixtures/lorem.txt');

        $this->assertSame(-1, call_user_func_array(new TypeComparator(), array($a, $b)));
    }
}
