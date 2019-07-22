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

    private static $data = [
        'Entries' => [
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ],
        'Foo' => 'Bar',
        'Version' => '2.0',
    ];

    private static $fallbackData = [
        'Entries' => [
            'Foo' => 'Foo',
            'Bam' => 'Lah',
        ],
        'Baz' => 'Foo',
        'Version' => '1.0',
    ];

    private static $mergedData = [
        // no recursive merging -> too complicated
        'Entries' => [
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ],
        'Baz' => 'Foo',
        'Version' => '2.0',
        'Foo' => 'Bar',
    ];

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
            ->willReturn(self::$data);

        $this->assertSame(self::$data, $this->reader->read(self::RES_DIR, 'root'));
    }

    public function testReadEntireDataFileIfNoIndicesGiven()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->willReturn(self::$data);

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::$fallbackData);

        $this->assertSame(self::$mergedData, $this->reader->readEntry(self::RES_DIR, 'en', []));
    }

    public function testReadExistingEntry()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::$data);

        $this->assertSame('Bar', $this->reader->readEntry(self::RES_DIR, 'root', ['Entries', 'Foo']));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testReadNonExistingEntry()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::$data);

        $this->reader->readEntry(self::RES_DIR, 'root', ['Entries', 'NonExisting']);
    }

    public function testFallbackIfEntryDoesNotExist()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn(self::$data);

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->willReturn(self::$fallbackData);

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam']));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testDontFallbackIfEntryDoesNotExistAndFallbackDisabled()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn(self::$data);

        $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam'], false);
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
            ->willReturn(self::$fallbackData);

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam']));
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

        $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam'], false);
    }

    public function provideMergeableValues()
    {
        return [
            ['foo', null, 'foo'],
            [null, 'foo', 'foo'],
            [['foo', 'bar'], null, ['foo', 'bar']],
            [['foo', 'bar'], [], ['foo', 'bar']],
            [null, ['baz'], ['baz']],
            [[], ['baz'], ['baz']],
            [['foo', 'bar'], ['baz'], ['baz', 'foo', 'bar']],
        ];
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
                ->willReturn($childData);

            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'root')
                ->willReturn($parentData);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn($childData);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en', [], true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testDontMergeDataIfFallbackDisabled($childData, $parentData, $result)
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn($childData);

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', [], false));
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
                ->willReturn(['Foo' => ['Bar' => $childData]]);

            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'root')
                ->willReturn(['Foo' => ['Bar' => $parentData]]);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en', ['Foo', 'Bar'], true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeNonExistingEntryWithExistingFallbackEntry($childData, $parentData, $result)
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn(['Foo' => 'Baz']);

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->willReturn(['Foo' => ['Bar' => $parentData]]);

        $this->assertSame($parentData, $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
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
                ->willReturn(['Foo' => ['Bar' => $childData]]);

            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn(['Foo' => 'Bar']);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testFailIfEntryFoundNeitherInParentNorChild()
    {
        $this->readerImpl->expects($this->at(0))
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn(['Foo' => 'Baz']);

        $this->readerImpl->expects($this->at(1))
            ->method('read')
            ->with(self::RES_DIR, 'en')
            ->willReturn(['Foo' => 'Bar']);

        $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true);
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
                ->willReturn(['Foo' => ['Bar' => $childData]]);

            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn(['Foo' => ['Bar' => $parentData]]);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testFollowLocaleAliases($childData, $parentData, $result)
    {
        $this->reader->setLocaleAliases(['mo' => 'ro_MD']);

        if (null === $childData || \is_array($childData)) {
            $this->readerImpl->expects($this->at(0))
                ->method('read')
                ->with(self::RES_DIR, 'ro_MD')
                ->willReturn(['Foo' => ['Bar' => $childData]]);

            // Read fallback locale of aliased locale ("ro_MD" -> "ro")
            $this->readerImpl->expects($this->at(1))
                ->method('read')
                ->with(self::RES_DIR, 'ro')
                ->willReturn(['Foo' => ['Bar' => $parentData]]);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'ro_MD')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'mo', ['Foo', 'Bar'], true));
    }
}
