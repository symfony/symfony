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

use Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class StructuredBundleReaderTest extends \PHPUnit_Framework_TestCase
{
    const RES_DIR = '/res/dir';

    /**
     * @var StructuredBundleReader
     */
    private $reader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $readerImpl;

    protected function setUp()
    {
        $this->readerImpl = $this->getMock('Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReaderInterface');
        $this->reader = new StructuredBundleReader($this->readerImpl);
    }

    public function testGetLocales()
    {
        $locales = array('en', 'de', 'fr');

        $this->readerImpl->expects($this->once())
            ->method('getLocales')
            ->with(self::RES_DIR)
            ->will($this->returnValue($locales));

        $this->assertSame($locales, $this->reader->getLocales(self::RES_DIR));
    }

    public function testRead()
    {
        $data = array('foo', 'bar');

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue($data));

        $this->assertSame($data, $this->reader->read(self::RES_DIR, 'en'));
    }

    public function testReadEntryNoParams()
    {
        $data = array('foo', 'bar');

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue($data));

        $this->assertSame($data, $this->reader->readEntry(self::RES_DIR, 'en', array()));
    }

    public function testReadEntryWithParam()
    {
        $data = array('Foo' => array('Bar' => 'Baz'));

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue($data));

        $this->assertSame('Baz', $this->reader->readEntry(self::RES_DIR, 'en', array('Foo', 'Bar')));
    }

    public function testReadEntryWithUnresolvablePath()
    {
        $data = array('Foo' => 'Baz');

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue($data));

        $this->assertNull($this->reader->readEntry(self::RES_DIR, 'en', array('Foo', 'Bar')));
    }

    public function readMergedEntryProvider()
    {
        return array(
            array('foo', null, 'foo'),
            array(null, 'foo', 'foo'),
            array(array('foo', 'bar'), null, array('foo', 'bar')),
            array(array('foo', 'bar'), array(), array('foo', 'bar')),
            array(null, array('baz'), array('baz')),
            array(array(), array('baz'), array('baz')),
            array(array('foo', 'bar'), array('baz'), array('baz', 'foo', 'bar')),
        );
    }

    /**
     * @dataProvider readMergedEntryProvider
     */
    public function testReadMergedEntryNoParams($childData, $parentData, $result)
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue($childData));

        if (null === $childData || is_array($childData)) {
            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue($parentData));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en_GB', array(), true));
    }

    /**
     * @dataProvider readMergedEntryProvider
     */
    public function testReadMergedEntryWithParams($childData, $parentData, $result)
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));

        if (null === $childData || is_array($childData)) {
            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue(array('Foo' => array('Bar' => $parentData))));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    public function testReadMergedEntryWithUnresolvablePath()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(array('Foo' => 'Baz')));

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue(array('Foo' => 'Bar')));

        $this->assertNull($this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    public function testReadMergedEntryWithUnresolvablePathInParent()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(array('Foo' => array('Bar' => array('three')))));

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue(array('Foo' => 'Bar')));

        $result = array('three');

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    public function testReadMergedEntryWithUnresolvablePathInChild()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(array('Foo' => 'Baz')));

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue(array('Foo' => array('Bar' => array('one', 'two')))));

        $result = array('one', 'two');

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    /**
     * @dataProvider readMergedEntryProvider
     */
    public function testReadMergedEntryWithTraversables($childData, $parentData, $result)
    {
        $parentData = is_array($parentData) ? new \ArrayObject($parentData) : $parentData;
        $childData = is_array($childData) ? new \ArrayObject($childData) : $childData;

        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));

        if (null === $childData || $childData instanceof \ArrayObject) {
            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue(array('Foo' => array('Bar' => $parentData))));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }
}
