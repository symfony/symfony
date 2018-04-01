<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Finder\Tests\Iterator;

use Symphony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class RecursiveDirectoryIteratorTest extends IteratorTestCase
{
    /**
     * @group network
     */
    public function testRewindOnFtp()
    {
        try {
            $i = new RecursiveDirectoryIterator('ftp://speedtest.tele2.net/', \RecursiveDirectoryIterator::SKIP_DOTS);
        } catch (\UnexpectedValueException $e) {
            $this->markTestSkipped('Unsupported stream "ftp".');
        }

        $i->rewind();

        $this->assertTrue(true);
    }

    /**
     * @group network
     */
    public function testSeekOnFtp()
    {
        try {
            $i = new RecursiveDirectoryIterator('ftp://speedtest.tele2.net/', \RecursiveDirectoryIterator::SKIP_DOTS);
        } catch (\UnexpectedValueException $e) {
            $this->markTestSkipped('Unsupported stream "ftp".');
        }

        $contains = array(
            'ftp://speedtest.tele2.net'.DIRECTORY_SEPARATOR.'1000GB.zip',
            'ftp://speedtest.tele2.net'.DIRECTORY_SEPARATOR.'100GB.zip',
        );
        $actual = array();

        $i->seek(0);
        $actual[] = $i->getPathname();

        $i->seek(1);
        $actual[] = $i->getPathname();

        $this->assertEquals($contains, $actual);
    }
}
