<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Resource\ResourceCollection;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

final class ResourceCollectionTest extends TestCase
{
    public function testResourcesAreLoadedOnceAndAggregateBytesAreReservedBeforeRetention()
    {
        $calls = 0;
        $collection = new ResourceCollection(new ResourceLoader(static function (string $source) use (&$calls): string {
            ++$calls;

            return $source;
        }), 3, 5);
        self::assertSame('abc', $collection->load('abc'));
        self::assertSame('abc', $collection->load('abc'));
        self::assertNull($collection->load('def'));
        self::assertSame('xy', $collection->load('xy'));
        self::assertSame(3, $calls);
    }

    public function testDataUrlsUseTheSameAggregateAndSourceBudgets()
    {
        $collection = new ResourceCollection(new ResourceLoader(), 3, 5);
        self::assertSame('abc', $collection->load('data:text/plain,abc'));
        self::assertSame('abc', $collection->load('data:text/plain,abc'));
        self::assertNull($collection->load('data:text/plain,def'));
        self::assertSame('xy', $collection->load('data:text/plain,xy'));
        self::assertNull($collection->load('data:text/plain,z'));
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6R5sAAAAASUVORK5CYII=');
        $collection = new ResourceCollection(new ResourceLoader(), 3, 1024, 1);
        self::assertNotNull($collection->image('data:image/png;base64,'.base64_encode($png)));
        self::assertNull($collection->image('data:image/png;base64,'.base64_encode($png.'different')));
    }

    public function testDecodedPixelBudgetCountsEachResourceOnlyOnce()
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6R5sAAAAASUVORK5CYII=');
        $collection = new ResourceCollection(new ResourceLoader(static fn (string $source): string => $png.$source), 3, 1024, 1);
        self::assertNotNull($first = $collection->image('a'));
        self::assertSame($first, $collection->image('a'));
        self::assertNull($collection->image('b'));
    }

    public function testInlineSvgStyleChangesReplaceTheSameResourceSlot()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><svg width="10" height="10"><rect width="10" height="10"/></svg>');
        $svg = $document->querySelector('svg');
        $collection = new ResourceCollection(new ResourceLoader(), 1, 2048);
        foreach (['red', 'green', 'blue', 'yellow'] as $color) {
            $image = $collection->svg($svg, static fn (): array => ['fill' => $color]);
            self::assertNotNull($image);
            self::assertStringContainsString('fill:'.$color, $image->data);
        }
    }

    public function testResourceCountIncludesFailedSources()
    {
        $calls = 0;
        $collection = new ResourceCollection(new ResourceLoader(static function () use (&$calls): string {
            ++$calls;
            throw new \RuntimeException();
        }), 2);
        self::assertNull($collection->load('a'));
        self::assertNull($collection->load('b'));
        self::assertNull($collection->load('c'));
        self::assertSame(2, $calls);
    }

    public function testReleasingDocumentRefundsOnlyItsInlineSvgBytesAndSlots()
    {
        $first = \Dom\HTMLDocument::createFromString('<!doctype html><svg width="10" height="10"><rect width="10" height="10"/></svg>');
        $second = \Dom\HTMLDocument::createFromString('<!doctype html><svg width="10" height="10"><rect width="10" height="10"/></svg>');
        $loader = new ResourceLoader(static fn (string $source): string => $source);
        $bytes = \strlen(new ResourceCollection($loader)->svg($first->querySelector('svg'))->data);
        $collection = new ResourceCollection($loader, 2, $bytes + 3);
        self::assertSame('abc', $collection->load('abc'));
        self::assertNotNull($collection->svg($first->querySelector('svg')));
        self::assertNull($collection->svg($second->querySelector('svg')));
        $collection->releaseDocument($first);
        self::assertNotNull($secondImage = $collection->svg($second->querySelector('svg')));
        $collection->releaseDocument($first);
        self::assertSame($secondImage, $collection->svg($second->querySelector('svg')));
        self::assertSame('abc', $collection->load('abc'));
        self::assertNull($collection->load('def'));
    }
}
