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
     * @group integration
     */
    public function testRewindOnFtp()
    {
        if (!getenv('INTEGRATION_FTP_URL')) {
            self::markTestSkipped('INTEGRATION_FTP_URL env var is not defined.');
        }

        $i = new RecursiveDirectoryIterator(getenv('INTEGRATION_FTP_URL').\DIRECTORY_SEPARATOR, \RecursiveDirectoryIterator::SKIP_DOTS);

        $i->rewind();

        $this->expectNotToPerformAssertions();
    }

    /**
     * @group network
     * @group integration
     */
    public function testSeekOnFtp()
    {
        if (!getenv('INTEGRATION_FTP_URL')) {
            self::markTestSkipped('INTEGRATION_FTP_URL env var is not defined.');
        }

        $ftpUrl = getenv('INTEGRATION_FTP_URL');

        $i = new RecursiveDirectoryIterator($ftpUrl.\DIRECTORY_SEPARATOR, \RecursiveDirectoryIterator::SKIP_DOTS);

        $contains = [
            $ftpUrl.\DIRECTORY_SEPARATOR.'pub',
            $ftpUrl.\DIRECTORY_SEPARATOR.'readme.txt',
        ];
        $actual = [];

        $i->seek(0);
        $actual[] = $i->getPathname();

        $i->seek(1);
        $actual[] = $i->getPathname();

        $this->assertEquals($contains, $actual);
    }

    public function testTrailingDirectorySeparatorIsStripped()
    {
        $fixturesDirectory = __DIR__.'/../Fixtures/';
        $actual = [];

        foreach (new RecursiveDirectoryIterator($fixturesDirectory, RecursiveDirectoryIterator::SKIP_DOTS) as $file) {
            $actual[] = $file->getPathname();
        }

        sort($actual);

        $expected = [
            $fixturesDirectory.'.dot',
            $fixturesDirectory.'A',
            $fixturesDirectory.'copy',
            $fixturesDirectory.'dolor.txt',
            $fixturesDirectory.'gitignore',
            $fixturesDirectory.'ipsum.txt',
            $fixturesDirectory.'lorem.txt',
            $fixturesDirectory.'one',
            $fixturesDirectory.'r+e.gex[c]a(r)s',
            $fixturesDirectory.'with space',
        ];

        $this->assertEquals($expected, $actual);
    }
}
