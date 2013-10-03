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

    public function testForwardCallToRead()
    {
        $data = array('foo', 'bar');

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue($data));

        $this->assertSame($data, $this->reader->read(self::RES_DIR, 'en'));
    }

    public function testReadCompleteDataFile()
    {
        $data = array('foo', 'bar');

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue($data));

        $this->assertSame($data, $this->reader->readEntry(self::RES_DIR, 'en', array()));
    }

    public function testReadExistingEntry()
    {
        $data = array('Foo' => array('Bar' => 'Baz'));

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue($data));

        $this->assertSame('Baz', $this->reader->readEntry(self::RES_DIR, 'en', array('Foo', 'Bar')));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\NoSuchEntryException
     */
    public function testReadNonExistingEntry()
    {
        $data = array('Foo' => 'Baz');

        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue($data));

        $this->reader->readEntry(self::RES_DIR, 'en', array('Foo', 'Bar'));
    }

    public function testFallbackIfEntryDoesNotExist()
    {
        $data = array('Foo' => 'Bar');

        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue($data));

        $fallbackData = array('Foo' => array('Bar' => 'Baz'));

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue($fallbackData));

        $this->assertSame('Baz', $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar')));
    }

    public function provideMergeableValues()
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
     * @dataProvider provideMergeableValues
     */
    public function testMergeDataWithFallbackData($childData, $parentData, $result)
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
     * @dataProvider provideMergeableValues
     */
    public function testDontMergeDataIfFallbackDisabled($childData, $parentData, $result)
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue($childData));

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', array(), false));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeExistingEntryWithExistingFallbackEntry($childData, $parentData, $result)
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

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeNonExistingEntryWithExistingFallbackEntry($childData, $parentData, $result)
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(array('Foo' => 'Baz')));

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue(array('Foo' => array('Bar' => $parentData))));

        $this->assertSame($parentData, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeExistingEntryWithNonExistingFallbackEntry($childData, $parentData, $result)
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));

        if (null === $childData || is_array($childData)) {
            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue(array('Foo' => 'Bar')));
        }

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\NoSuchEntryException
     */
    public function testFailIfEntryFoundNeitherInParentNorChild()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(array('Foo' => 'Baz')));

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue(array('Foo' => 'Bar')));

        $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true);
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeTraversables($childData, $parentData, $result)
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

    /**
     * @dataProvider provideMergeableValues
     */
    public function testFollowLocaleAliases($childData, $parentData, $result)
    {
        $this->reader->setLocaleAliases(array('mo' => 'ro_MD'));

        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'mo')
            ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));

        if (null === $childData || is_array($childData)) {
            // Read fallback locale of aliased locale ("ro_MD" -> "ro")
            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'ro')
                ->will($this->returnValue(array('Foo' => array('Bar' => $parentData))));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'mo', array('Foo', 'Bar'), true));
    }
}
