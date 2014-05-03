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

use Symfony\Component\Finder\Comparator\File\NameComparator;

class NameComparatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideComparableFileNames
     */
    public function testCompareDifferentFilenames($fileA, $fileB)
    {
        $a = new \SplFileInfo(__DIR__.'/../../Fixtures/'.$fileA);
        $b = new \SplFileInfo(__DIR__.'/../../Fixtures/'.$fileB);

        $this->assertNotSame(0, call_user_func_array(new NameComparator(), array($a, $b)));
    }

    public function provideComparableFileNames()
    {
        return array(
            array('dolor.txt', 'ipsum.txt'),
            array('ipsum.txt', 'dolor.txt'),
        );
    }

    public function testCompareSameFilenames()
    {
        $a = new \SplFileInfo(__DIR__.'/../../Fixtures/dolor.txt');
        $b = new \SplFileInfo(__DIR__.'/../../Fixtures/dolor.txt');

        $this->assertSame(0, call_user_func_array(new NameComparator(), array($a, $b)));
    }
}
