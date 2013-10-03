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

use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BinaryBundleReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var BinaryBundleReader
     */
    private $reader;

    protected function setUp()
    {
        IntlTestHelper::requireFullIntl($this);

        $this->reader = new BinaryBundleReader();
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

    public function testReadFollowsFallback()
    {
        // "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'ro_MD');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bam', $data['Baz']);
        $this->assertTrue(isset($data['Foo']), 'entries from the fallback locale are reported to be set...');
        $this->assertNull($data['Foo'], '...but are always NULL. WTF.');
        $this->assertFalse(isset($data['ExistsNot']));
    }

    public function testReadFollowsFallbackAlias()
    {
        // "mo" = "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'mo');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bam', $data['Baz'], 'data from the aliased locale can be accessed');
        $this->assertTrue(isset($data['Foo']), 'entries from the fallback locale are reported to be set...');
        $this->assertNull($data['Foo'], '...but are always NULL. WTF.');
        $this->assertFalse(isset($data['ExistsNot']));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\NoSuchLocaleException
     */
    public function testReadFailsIfNonExistingLocale()
    {
        $this->reader->read(__DIR__.'/Fixtures/res', 'foo');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\NoSuchLocaleException
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
