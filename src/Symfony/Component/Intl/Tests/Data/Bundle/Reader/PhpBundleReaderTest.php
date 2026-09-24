<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Data\Bundle\Reader;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Data\Bundle\Reader\PhpBundleReader;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PhpBundleReaderTest extends TestCase
{
    private PhpBundleReader $reader;
    private string $directory;

    protected function setUp(): void
    {
        $this->reader = new PhpBundleReader();
        $this->directory = sys_get_temp_dir().'/PhpBundleReaderTest/'.random_int(1000, 9999);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testReadReturnsArray()
    {
        $data = $this->reader->read(__DIR__.'/Fixtures/php', 'en');

        $this->assertIsArray($data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertArrayNotHasKey('ExistsNot', $data);
    }

    #[RequiresPhpExtension('zlib')]
    public function testReadCompressedFile()
    {
        mkdir($this->directory, 0o777, true);
        copy(__DIR__.'/Fixtures/php/en.php', 'compress.zlib://'.$this->directory.'/en.php.gz');

        $this->assertSame(['Foo' => 'Bar'], $this->reader->read($this->directory, 'en'));
    }

    #[RequiresPhpExtension('zlib')]
    public function testReadWhenBothPlainAndCompressedFilesExist()
    {
        mkdir($this->directory, 0o777, true);
        copy(__DIR__.'/Fixtures/php/en.php', $this->directory.'/en.php');
        copy(__DIR__.'/Fixtures/php/en.php', 'compress.zlib://'.$this->directory.'/en.php.gz');

        $this->assertSame(['Foo' => 'Bar'], $this->reader->read($this->directory, 'en'));
    }

    public function testReadFailsIfNonExistingLocale()
    {
        $this->expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/php', 'foo');
    }

    public function testReadFailsIfNonExistingDirectory()
    {
        $this->expectException(RuntimeException::class);
        $this->reader->read(__DIR__.'/foo', 'en');
    }

    public function testReadFailsIfNotAFile()
    {
        $this->expectException(RuntimeException::class);
        $this->reader->read(__DIR__.'/Fixtures/NotAFile', 'en');
    }

    public function testReaderDoesNotBreakOutOfGivenPath()
    {
        $this->expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/php', '../invalid_directory/en');
    }
}
