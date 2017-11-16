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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PhpBundleReaderTest extends TestCase
{
    /**
     * @var PhpBundleReader
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = new PhpBundleReader();
    }

    public function testReadReturnsArray()
    {
        $data = $this->reader->read(__DIR__.'/Fixtures/php', 'en');

        $this->assertInternalType('array', $data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\ResourceBundleNotFoundException
     */
    public function testReadFailsIfNonExistingLocale()
    {
        $this->reader->read(__DIR__.'/Fixtures/php', 'foo');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\RuntimeException
     */
    public function testReadFailsIfNonExistingDirectory()
    {
        $this->reader->read(__DIR__.'/foo', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\RuntimeException
     */
    public function testReadFailsIfNotAFile()
    {
        $this->reader->read(__DIR__.'/Fixtures/NotAFile', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\ResourceBundleNotFoundException
     */
    public function testReaderDoesNotBreakOutOfGivenPath()
    {
        $this->reader->read(__DIR__.'/Fixtures/php', '../invalid_directory/en');
    }
}
