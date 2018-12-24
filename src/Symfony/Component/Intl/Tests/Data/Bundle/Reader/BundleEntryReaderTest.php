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
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReader;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BundleEntryReaderTest extends TestCase
{
    const RES_DIR = '/res/dir';

    /**
     * @var BundleEntryReader
     */
    private $reader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $readerImpl;

    private static $data = array(
        'Entries' => array(
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ),
        'Foo' => 'Bar',
        'Version' => '2.0',
    );

    private static $fallbackData = array(
        'Entries' => array(
            'Foo' => 'Foo',
            'Bam' => 'Lah',
        ),
        'Baz' => 'Foo',
        'Version' => '1.0',
    );

    private static $mergedData = array(
        // no recursive merging -> too complicated
        'Entries' => array(
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ),
        'Baz' => 'Foo',
        'Version' => '2.0',
        'Foo' => 'Bar',
    );

    protected function setUp()
    {
        $this->readerImpl = $this->getMockBuilder('Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface')->getMock();
        $this->reader = new BundleEntryReader($this->readerImpl);
    }

    public function testForwardCallToRead()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->will($this->returnValue(self::$data));

        $this->assertSame(self::$data, $this->reader->read(self::RES_DIR, 'root'));
    }

    public function testReadEntireDataFileIfNoIndicesGiven()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue(self::$data));

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->will($this->returnValue(self::$fallbackData));

        $this->assertSame(self::$mergedData, $this->reader->readEntry(self::RES_DIR, 'en', array()));
    }

    public function testReadExistingEntry()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->will($this->returnValue(self::$data));

        $this->assertSame('Bar', $this->reader->readEntry(self::RES_DIR, 'root', array('Entries', 'Foo')));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testReadNonExistingEntry()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->will($this->returnValue(self::$data));

        $this->reader->readEntry(self::RES_DIR, 'root', array('Entries', 'NonExisting'));
    }

    public function testFallbackIfEntryDoesNotExist()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(self::$data));

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue(self::$fallbackData));

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Entries', 'Bam')));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testDontFallbackIfEntryDoesNotExistAndFallbackDisabled()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->will($this->returnValue(self::$data));

        $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Entries', 'Bam'), false);
    }

    public function testFallbackIfLocaleDoesNotExist()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willThrowException(new ResourceBundleNotFoundException());

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->will($this->returnValue(self::$fallbackData));

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Entries', 'Bam')));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testDontFallbackIfLocaleDoesNotExistAndFallbackDisabled()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willThrowException(new ResourceBundleNotFoundException());

        $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Entries', 'Bam'), false);
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
        if (null === $childData || \is_array($childData)) {
            $this->readerImpl->expects($this->at(0))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue($childData));

            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'root')
                ->will($this->returnValue($parentData));
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue($childData));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en', array(), true));
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
        if (null === $childData || \is_array($childData)) {
            $this->readerImpl->expects($this->at(0))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));

            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'root')
                ->will($this->returnValue(array('Foo' => array('Bar' => $parentData))));
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en', array('Foo', 'Bar'), true));
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
        if (null === $childData || \is_array($childData)) {
            $this->readerImpl->expects($this->at(0))
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));

            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue(array('Foo' => 'Bar')));
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));
        }

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
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
        $parentData = \is_array($parentData) ? new \ArrayObject($parentData) : $parentData;
        $childData = \is_array($childData) ? new \ArrayObject($childData) : $childData;

        if (null === $childData || $childData instanceof \ArrayObject) {
            $this->readerImpl->expects($this->at(0))
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));

            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->will($this->returnValue(array('Foo' => array('Bar' => $parentData))));
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en_GB', array('Foo', 'Bar'), true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testFollowLocaleAliases($childData, $parentData, $result)
    {
        $this->reader->setLocaleAliases(array('mo' => 'ro_MD'));

        if (null === $childData || \is_array($childData)) {
            $this->readerImpl->expects($this->at(0))
                ->method('read')
                ->with(self::RES_DIR, 'ro_MD')
                ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));

            // Read fallback locale of aliased locale ("ro_MD" -> "ro")
            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'ro')
                ->will($this->returnValue(array('Foo' => array('Bar' => $parentData))));
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'ro_MD')
                ->will($this->returnValue(array('Foo' => array('Bar' => $childData))));
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'mo', array('Foo', 'Bar'), true));
    }
}
