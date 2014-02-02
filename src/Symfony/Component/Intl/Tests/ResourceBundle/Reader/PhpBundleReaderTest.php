<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\ResourceBundle\Reader;

use Symfony\Component\Intl\ResourceBundle\Reader\PhpBundleReader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PhpBundleReaderTest extends \PHPUnit_Framework_TestCase
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
        $data = $this->reader->read(__DIR__ . '/Fixtures', 'en');

        $this->assertTrue(is_array($data));
        $this->assertSame('Bar', $data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testReadFailsIfLocaleOtherThanEn()
    {
        $this->reader->read(__DIR__ . '/Fixtures', 'foo');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\RuntimeException
     */
    public function testReadFailsIfNonExistingDirectory()
    {
        $this->reader->read(__DIR__ . '/foo', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\RuntimeException
     */
    public function testReadFailsIfNotAFile()
    {
        $this->reader->read(__DIR__ . '/Fixtures/NotAFile', 'en');
    }
}
