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
use Symfony\Component\Intl\Data\Bundle\Reader\JsonBundleReader;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonBundleReaderTest extends TestCase
{
    /**
     * @var JsonBundleReader
     */
    private $reader;

    protected function setUp(): void
    {
        $this->reader = new JsonBundleReader();
    }

    public function testReadReturnsArray()
    {
        $data = $this->reader->read(__DIR__.'/Fixtures/json', 'en');

        $this->assertIsArray($data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadFailsIfNonExistingLocale()
    {
        $this->expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/json', 'foo');
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

    public function testReadFailsIfInvalidJson()
    {
        $this->expectException(RuntimeException::class);
        $this->reader->read(__DIR__.'/Fixtures/json', 'en_Invalid');
    }

    public function testReaderDoesNotBreakOutOfGivenPath()
    {
        $this->expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/json', '../invalid_directory/en');
    }
}
