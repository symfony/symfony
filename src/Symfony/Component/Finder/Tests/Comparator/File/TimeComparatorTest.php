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

abstract class TimeComparatorTest extends \PHPUnit_Framework_TestCase
{
    protected $fileA;
    protected $fileB;

    protected function tearDown()
    {
        if (file_exists($this->fileA)) {
            @unlink($this->fileA);
        }

        if (file_exists($this->fileB)) {
            @unlink($this->fileB);
        }

        clearstatcache();
    }

    protected function touchFiles($accessTimeA, $accessTimeB)
    {
        $this->fileA = sys_get_temp_dir().'/file-a.txt';
        $this->fileB = sys_get_temp_dir().'/file-b.txt';

        touch($this->fileA, $accessTimeA, $accessTimeA);
        touch($this->fileB, $accessTimeB, $accessTimeB);
    }

    protected function touchAndModifyFiles($modifiedTimeA, $modifiedTimeB)
    {
        // Create files
        $this->touchFiles($modifiedTimeA - 3600, $modifiedTimeB - 3600);

        // Modify them
        touch($this->fileA, $modifiedTimeA);
        touch($this->fileB, $modifiedTimeB);
    }
}
