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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReader;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BundleEntryReaderTest extends TestCase
{
    private const RES_DIR = '/res/dir';

    /**
     * @var BundleEntryReader
     */
    private $reader;

    /**
     * @var MockObject&BundleEntryReaderInterface
     */
    private $readerImpl;

    private const DATA = [
        'Entries' => [
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ],
        'Foo' => 'Bar',
        'Version' => '2.0',
    ];

    private const FALLBACK_DATA = [
        'Entries' => [
            'Foo' => 'Foo',
            'Bam' => 'Lah',
        ],
        'Baz' => 'Foo',
        'Version' => '1.0',
    ];

    private const MERGED_DATA = [
        // no recursive merging -> too complicated
        'Entries' => [
            'Foo' => 'Bar',
            'Bar' => 'Baz',
        ],
        'Baz' => 'Foo',
        'Version' => '2.0',
        'Foo' => 'Bar',
    ];

    protected function setUp(): void
    {
        $this->readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $this->reader = new BundleEntryReader($this->readerImpl);
    }

    public function testForwardCallToRead()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::DATA);

        $this->assertSame(self::DATA, $this->reader->read(self::RES_DIR, 'root'));
    }

    public function testReadEntireDataFileIfNoIndicesGiven()
    {
        $this->readerImpl->expects($this->exactly(2))
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en'],
                [self::RES_DIR, 'root']
            )
            ->willReturnOnConsecutiveCalls(self::DATA, self::FALLBACK_DATA);

        $this->assertSame(self::MERGED_DATA, $this->reader->readEntry(self::RES_DIR, 'en', []));
    }

    public function testReadExistingEntry()
    {
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::DATA);

        $this->assertSame('Bar', $this->reader->readEntry(self::RES_DIR, 'root', ['Entries', 'Foo']));
    }

    public function testReadNonExistingEntry()
    {
        $this->expectException(MissingResourceException::class);
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::DATA);

        $this->reader->readEntry(self::RES_DIR, 'root', ['Entries', 'NonExisting']);
    }

    public function testFallbackIfEntryDoesNotExist()
    {
        $this->readerImpl->expects($this->exactly(2))
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en_GB'],
                [self::RES_DIR, 'en']
            )
            ->willReturnOnConsecutiveCalls(self::DATA, self::FALLBACK_DATA);

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam']));
    }

    public function testDontFallbackIfEntryDoesNotExistAndFallbackDisabled()
    {
        $this->expectException(MissingResourceException::class);
        $this->readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn(self::DATA);

        $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam'], false);
    }

    public function testFallbackIfLocaleDoesNotExist()
    {
        $this->readerImpl->expects($this->exactly(2))
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en_GB'],
                [self::RES_DIR, 'en']
            )
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new ResourceBundleNotFoundException()),
                self::FALLBACK_DATA
            );

        $this->assertSame('Lah', $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam']));
    }

    public function testDontFallbackIfLocaleDoesNotExistAndFallbackDisabled()
    {
        $this->expectException(MissingResourceException::class);
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
            $this->readerImpl->expects($this->exactly(2))
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'en'],
                    [self::RES_DIR, 'root']
                )
                ->willReturnOnConsecutiveCalls($childData, $parentData);
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
            $this->readerImpl->expects($this->exactly(2))
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'en'],
                    [self::RES_DIR, 'root']
                )
                ->willReturnOnConsecutiveCalls(
                    ['Foo' => ['Bar' => $childData]],
                    ['Foo' => ['Bar' => $parentData]]
                );
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
        $this->readerImpl
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en_GB'],
                [self::RES_DIR, 'en']
            )
            ->willReturnOnConsecutiveCalls(['Foo' => 'Baz'], ['Foo' => ['Bar' => $parentData]]);

        $this->assertSame($parentData, $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    /**
     * @dataProvider provideMergeableValues
     */
    public function testMergeExistingEntryWithNonExistingFallbackEntry($childData, $parentData, $result)
    {
        if (null === $childData || \is_array($childData)) {
            $this->readerImpl
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'en_GB'],
                    [self::RES_DIR, 'en']
                )
                ->willReturnOnConsecutiveCalls(['Foo' => ['Bar' => $childData]], ['Foo' => 'Bar']);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($childData, $this->reader->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    public function testFailIfEntryFoundNeitherInParentNorChild()
    {
        $this->expectException(MissingResourceException::class);
        $this->readerImpl
            ->method('read')
            ->withConsecutive(
                [self::RES_DIR, 'en_GB'],
                [self::RES_DIR, 'en']
            )
            ->willReturnOnConsecutiveCalls(['Foo' => 'Baz'], ['Foo' => 'Bar']);

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
            $this->readerImpl
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'en_GB'],
                    [self::RES_DIR, 'en']
                )
                ->willReturnOnConsecutiveCalls(['Foo' => ['Bar' => $childData]], ['Foo' => ['Bar' => $parentData]]);
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
            $this->readerImpl
                ->method('read')
                ->withConsecutive(
                    [self::RES_DIR, 'ro_MD'],
                    // Read fallback locale of aliased locale ("ro_MD" -> "ro")
                    [self::RES_DIR, 'ro']
                )
                ->willReturnOnConsecutiveCalls(['Foo' => ['Bar' => $childData]], ['Foo' => ['Bar' => $parentData]]);
        } else {
            $this->readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'ro_MD')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($result, $this->reader->readEntry(self::RES_DIR, 'mo', ['Foo', 'Bar'], true));
    }
}
