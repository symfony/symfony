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

use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class RecursiveDirectoryIteratorTest extends IteratorTestCase
{
    /**
     * @group network
     */
    public function testRewindOnFtp()
    {
        try {
            $i = new RecursiveDirectoryIterator('ftp://speedtest:speedtest@ftp.otenet.gr/', \RecursiveDirectoryIterator::SKIP_DOTS);
        } catch (\UnexpectedValueException $e) {
            self::markTestSkipped('Unsupported stream "ftp".');
        }

        $i->rewind();

        self::assertTrue(true);
    }

    /**
     * @group network
     */
    public function testSeekOnFtp()
    {
        try {
            $i = new RecursiveDirectoryIterator('ftp://speedtest:speedtest@ftp.otenet.gr/', \RecursiveDirectoryIterator::SKIP_DOTS);
        } catch (\UnexpectedValueException $e) {
            self::markTestSkipped('Unsupported stream "ftp".');
        }

        $contains = [
            'ftp://speedtest:speedtest@ftp.otenet.gr'.\DIRECTORY_SEPARATOR.'test100Mb.db',
            'ftp://speedtest:speedtest@ftp.otenet.gr'.\DIRECTORY_SEPARATOR.'test100k.db',
        ];
        $actual = [];

        $i->seek(0);
        $actual[] = $i->getPathname();

        $i->seek(1);
        $actual[] = $i->getPathname();

        self::assertEquals($contains, $actual);
    }
}
