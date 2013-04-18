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
        $data = $this->reader->read(__DIR__ . '/Fixtures', 'en');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\RuntimeException
     */
    public function testReadFailsIfNonExistingLocale()
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
}
