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
    protected function setUp(): void
    {
        if (!\in_array('ftp', stream_get_wrappers(), true) || !\ini_get('allow_url_fopen')) {
            $this->markTestSkipped('Unsupported stream "ftp".');
        }
    }

    /**
     * @group network
     */
    public function testRewindOnFtp()
    {
        $i = new RecursiveDirectoryIterator('ftp://speedtest:speedtest@ftp.otenet.gr/', \RecursiveDirectoryIterator::SKIP_DOTS);

        $i->rewind();

        $this->assertTrue(true);
    }

    /**
     * @group network
     */
    public function testSeekOnFtp()
    {
        $i = new RecursiveDirectoryIterator('ftp://speedtest:speedtest@ftp.otenet.gr/', \RecursiveDirectoryIterator::SKIP_DOTS);

        $contains = [
            'ftp://speedtest:speedtest@ftp.otenet.gr'.\DIRECTORY_SEPARATOR.'test100Mb.db',
            'ftp://speedtest:speedtest@ftp.otenet.gr'.\DIRECTORY_SEPARATOR.'test100k.db',
        ];
        $actual = [];

        $i->seek(0);
        $actual[] = $i->getPathname();

        $i->seek(1);
        $actual[] = $i->getPathname();

        $this->assertEquals($contains, $actual);
    }
}
