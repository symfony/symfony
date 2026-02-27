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

use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testForwardCallToRead()
    {
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::DATA);

        $this->assertSame(self::DATA, $this->getReader($readerImpl)->read(self::RES_DIR, 'root'));
    }

    public function testReadEntireDataFileIfNoIndicesGiven()
    {
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $readerImpl->expects($this->exactly(2))
            ->method('read')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    [[self::RES_DIR, 'en'], self::DATA],
                    [[self::RES_DIR, 'root'], self::FALLBACK_DATA],
                ];

                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        $this->assertSame(self::MERGED_DATA, $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en', []));
    }

    public function testReadExistingEntry()
    {
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::DATA);

        $this->assertSame('Bar', $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'root', ['Entries', 'Foo']));
    }

    public function testReadNonExistingEntry()
    {
        $this->expectException(MissingResourceException::class);
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'root')
            ->willReturn(self::DATA);

        $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'root', ['Entries', 'NonExisting']);
    }

    public function testFallbackIfEntryDoesNotExist()
    {
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $readerImpl->expects($this->exactly(2))
            ->method('read')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    [[self::RES_DIR, 'en_GB'], self::DATA],
                    [[self::RES_DIR, 'en'], self::FALLBACK_DATA],
                ];

                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        $this->assertSame('Lah', $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam']));
    }

    public function testDontFallbackIfEntryDoesNotExistAndFallbackDisabled()
    {
        $this->expectException(MissingResourceException::class);
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn(self::DATA);

        $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam'], false);
    }

    public function testFallbackIfLocaleDoesNotExist()
    {
        $exception = new ResourceBundleNotFoundException();
        $series = [
            [[self::RES_DIR, 'en_GB'], $exception],
            [[self::RES_DIR, 'en'], self::FALLBACK_DATA],
        ];

        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $readerImpl->expects($this->exactly(2))
            ->method('read')
            ->willReturnCallback(function (...$args) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                if ($return instanceof \Exception) {
                    throw $return;
                }

                return $return;
            })
        ;

        $this->assertSame('Lah', $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam']));
    }

    public function testDontFallbackIfLocaleDoesNotExistAndFallbackDisabled()
    {
        $this->expectException(MissingResourceException::class);
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willThrowException(new ResourceBundleNotFoundException());

        $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en_GB', ['Entries', 'Bam'], false);
    }

    public static function provideMergeableValues()
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

    #[DataProvider('provideMergeableValues')]
    public function testMergeDataWithFallbackData($childData, $parentData, $result)
    {
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);

        if (null === $childData || \is_array($childData)) {
            $series = [
                [[self::RES_DIR, 'en'], $childData],
                [[self::RES_DIR, 'root'], $parentData],
            ];

            $readerImpl->expects($this->exactly(2))
                ->method('read')
                ->willReturnCallback(function (...$args) use (&$series) {
                    [$expectedArgs, $return] = array_shift($series);
                    $this->assertSame($expectedArgs, $args);

                    return $return;
                })
            ;
        } else {
            $readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn($childData);
        }

        $this->assertSame($result, $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en', [], true));
    }

    #[DataProvider('provideMergeableValues')]
    public function testDontMergeDataIfFallbackDisabled($childData, $parentData, $result)
    {
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
        $readerImpl->expects($this->once())
            ->method('read')
            ->with(self::RES_DIR, 'en_GB')
            ->willReturn($childData);

        $this->assertSame($childData, $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en_GB', [], false));
    }

    #[DataProvider('provideMergeableValues')]
    public function testMergeExistingEntryWithExistingFallbackEntry($childData, $parentData, $result)
    {
        $readerImpl = $this->createMock(BundleEntryReaderInterface::class);

        if (null === $childData || \is_array($childData)) {
            $series = [
                [[self::RES_DIR, 'en'], ['Foo' => ['Bar' => $childData]]],
                [[self::RES_DIR, 'root'], ['Foo' => ['Bar' => $parentData]]],
            ];

            $readerImpl->expects($this->exactly(2))
                ->method('read')
                ->willReturnCallback(function (...$args) use (&$series) {
                    [$expectedArgs, $return] = array_shift($series);
                    $this->assertSame($expectedArgs, $args);

                    return $return;
                })
            ;
        } else {
            $readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($result, $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en', ['Foo', 'Bar'], true));
    }

    #[DataProvider('provideMergeableValues')]
    public function testMergeNonExistingEntryWithExistingFallbackEntry($childData, $parentData, $result)
    {
        $series = [
            [[self::RES_DIR, 'en_GB'], ['Foo' => 'Baz']],
            [[self::RES_DIR, 'en'], ['Foo' => ['Bar' => $parentData]]],
        ];

        $readerImpl = $this->createStub(BundleEntryReaderInterface::class);
        $readerImpl
            ->method('read')
            ->willReturnCallback(static function (...$args) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);

                return $expectedArgs === $args ? $return : null;
            })
        ;

        $this->assertSame($parentData, $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    #[DataProvider('provideMergeableValues')]
    public function testMergeExistingEntryWithNonExistingFallbackEntry($childData, $parentData, $result)
    {
        if (null === $childData || \is_array($childData)) {
            $series = [
                [[self::RES_DIR, 'en_GB'], ['Foo' => ['Bar' => $childData]]],
                [[self::RES_DIR, 'en'], ['Foo' => 'Bar']],
            ];

            $readerImpl = $this->createStub(BundleEntryReaderInterface::class);
            $readerImpl
                ->method('read')
                ->willReturnCallback(static function (...$args) use (&$series) {
                    [$expectedArgs, $return] = array_shift($series);

                    return $expectedArgs === $args ? $return : null;
                })
            ;
        } else {
            $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
            $readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($childData, $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    public function testFailIfEntryFoundNeitherInParentNorChild()
    {
        $this->expectException(MissingResourceException::class);

        $readerImpl = $this->createStub(BundleEntryReaderInterface::class);
        $readerImpl
            ->method('read')
            ->willReturnCallback(static function (...$args) {
                static $series = [
                    [[self::RES_DIR, 'en_GB'], ['Foo' => 'Baz']],
                    [[self::RES_DIR, 'en'], ['Foo' => 'Bar']],
                ];

                [$expectedArgs, $return] = array_shift($series);

                return $expectedArgs === $args ? $return : null;
            })
        ;

        $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true);
    }

    #[DataProvider('provideMergeableValues')]
    public function testMergeTraversables($childData, $parentData, $result)
    {
        $parentData = \is_array($parentData) ? new \ArrayObject($parentData) : $parentData;
        $childData = \is_array($childData) ? new \ArrayObject($childData) : $childData;

        if (null === $childData || $childData instanceof \ArrayObject) {
            $series = [
                [[self::RES_DIR, 'en_GB'], ['Foo' => ['Bar' => $childData]]],
                [[self::RES_DIR, 'en'], ['Foo' => ['Bar' => $parentData]]],
            ];

            $readerImpl = $this->createStub(BundleEntryReaderInterface::class);
            $readerImpl
                ->method('read')
                ->willReturnCallback(static function (...$args) use (&$series) {
                    [$expectedArgs, $return] = array_shift($series);

                    return $expectedArgs === $args ? $return : null;
                })
            ;
        } else {
            $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
            $readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'en_GB')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $this->assertSame($result, $this->getReader($readerImpl)->readEntry(self::RES_DIR, 'en_GB', ['Foo', 'Bar'], true));
    }

    #[DataProvider('provideMergeableValues')]
    public function testFollowLocaleAliases($childData, $parentData, $result)
    {
        if (null === $childData || \is_array($childData)) {
            $series = [
                [[self::RES_DIR, 'ro_MD'], ['Foo' => ['Bar' => $childData]]],
                // Read fallback locale of aliased locale ("ro_MD" -> "ro")
                [[self::RES_DIR, 'ro'], ['Foo' => ['Bar' => $parentData]]],
            ];

            $readerImpl = $this->createStub(BundleEntryReaderInterface::class);
            $readerImpl
                ->method('read')
                ->willReturnCallback(static function (...$args) use (&$series) {
                    [$expectedArgs, $return] = array_shift($series);

                    return $expectedArgs === $args ? $return : null;
                })
            ;
        } else {
            $readerImpl = $this->createMock(BundleEntryReaderInterface::class);
            $readerImpl->expects($this->once())
                ->method('read')
                ->with(self::RES_DIR, 'ro_MD')
                ->willReturn(['Foo' => ['Bar' => $childData]]);
        }

        $reader = $this->getReader($readerImpl);
        $reader->setLocaleAliases(['mo' => 'ro_MD']);

        $this->assertSame($result, $reader->readEntry(self::RES_DIR, 'mo', ['Foo', 'Bar'], true));
    }

    private function getReader(?BundleEntryReaderInterface $entryReader = null): BundleEntryReader
    {
        return new BundleEntryReader($entryReader ?? $this->createStub(BundleEntryReaderInterface::class));
    }
}
