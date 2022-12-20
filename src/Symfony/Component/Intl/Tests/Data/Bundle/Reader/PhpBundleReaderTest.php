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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Data\Bundle\Reader\PhpBundleReader;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PhpBundleReaderTest extends TestCase
{
    /**
     * @var PhpBundleReader
     */
    private $reader;

    protected function setUp(): void
    {
        $this->reader = new PhpBundleReader();
    }

    public function testReadReturnsArray()
    {
        $data = $this->reader->read(__DIR__.'/Fixtures/php', 'en');

        self::assertIsArray($data);
        self::assertSame('Bar', $data['Foo']);
        self::assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadFailsIfNonExistingLocale()
    {
        self::expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/php', 'foo');
    }

    public function testReadFailsIfNonExistingDirectory()
    {
        self::expectException(RuntimeException::class);
        $this->reader->read(__DIR__.'/foo', 'en');
    }

    public function testReadFailsIfNotAFile()
    {
        self::expectException(RuntimeException::class);
        $this->reader->read(__DIR__.'/Fixtures/NotAFile', 'en');
    }

    public function testReaderDoesNotBreakOutOfGivenPath()
    {
        self::expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/php', '../invalid_directory/en');
    }
}
