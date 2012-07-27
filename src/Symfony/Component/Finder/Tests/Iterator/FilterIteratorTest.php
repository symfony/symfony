<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Alex Bogomazov
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

class FilterIteratorTest extends RealIteratorTestCase
{
    public function testFilterFilesystemIterators()
    {
        $tmpDir = sys_get_temp_dir().'/symfony2_finder';


        $i = new \FilesystemIterator($tmpDir);

        // it is expected that there are test.py test.php in the tmpDir
        $i = $this->getMockForAbstractClass('Symfony\Component\Finder\Iterator\FilterIterator', array($i));
        $i->expects($this->any())->method('accept')->will($this->returnCallback(function () use ($i) {
            return (bool)preg_match('/\.php/', (string)$i->current());
        }));

        $c = 0;
        foreach ($i as $item) {
            $c++;
        }
        // This works
        $this->assertEquals(1, $c);


        $i->rewind();

        $c = 0;
        foreach ($i as $item) {
            $c++;
        }
        // This would fail with \FilterIterator but works with Symfony\Component\Finder\Iterator\FilterIterator
        $this->assertEquals(1, $c);
    }
}
