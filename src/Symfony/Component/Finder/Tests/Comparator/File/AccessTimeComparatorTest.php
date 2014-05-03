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

use Symfony\Component\Finder\Comparator\File\AccessTimeComparator;

class AccessTimeComparatorTest extends TimeComparatorTest
{
    /**
     * @dataProvider provideAccessTimes
     */
    public function testCompareAccessTimes($accessTimeA, $accessTimeB, $comparison)
    {
        $this->touchFiles($accessTimeA, $accessTimeB);

        $fileA = new \SplFileInfo($this->fileA);
        $fileB = new \SplFileInfo($this->fileB);

        $this->assertSame($comparison, call_user_func_array(new AccessTimeComparator(), array($fileA, $fileB)));
    }

    public function provideAccessTimes()
    {
        return array(
            array(time(), time(), 0),
            array(time(), time()-3600, 3600),
            array(time()-3600, time(), -3600),
        );
    }
}
