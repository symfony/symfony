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

use Symfony\Component\Intl\Data\Bundle\Reader\IntlBundleReader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @requires extension intl
 */
class IntlBundleReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var IntlBundleReader
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = new IntlBundleReader();
    }

    public function testReadReturnsArrayAccess()
    {
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'ro');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    public function testReadFollowsAlias()
    {
        // "alias" = "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'alias');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    public function testReadDoesNotFollowFallback()
    {
        if (PHP_VERSION_ID < 50307 || PHP_VERSION_ID === 50400) {
            $this->markTestSkipped('ResourceBundle handles disabling fallback properly only as of PHP 5.3.7 and 5.4.1.');
        }

        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('ResourceBundle does not support disabling fallback properly on HHVM.');
        }

        // "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'ro_MD');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bam', $data['Baz']);
        $this->assertFalse(isset($data['Foo']));
        $this->assertNull($data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    public function testReadDoesNotFollowFallbackAlias()
    {
        if (PHP_VERSION_ID < 50307 || PHP_VERSION_ID === 50400) {
            $this->markTestSkipped('ResourceBundle handles disabling fallback properly only as of PHP 5.3.7 and 5.4.1.');
        }

        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('ResourceBundle does not support disabling fallback properly on HHVM.');
        }

        // "mo" = "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'mo');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bam', $data['Baz'], 'data from the aliased locale can be accessed');
        $this->assertFalse(isset($data['Foo']));
        $this->assertNull($data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\ResourceBundleNotFoundException
     */
    public function testReadFailsIfNonExistingLocale()
    {
        $this->reader->read(__DIR__.'/Fixtures/res', 'foo');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\ResourceBundleNotFoundException
     */
    public function testReadFailsIfNonExistingFallbackLocale()
    {
        $this->reader->read(__DIR__.'/Fixtures/res', 'ro_AT');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\RuntimeException
     */
    public function testReadFailsIfNonExistingDirectory()
    {
        $this->reader->read(__DIR__.'/foo', 'ro');
    }
}
