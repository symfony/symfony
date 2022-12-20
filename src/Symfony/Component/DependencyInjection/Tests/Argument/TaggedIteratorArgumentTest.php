<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;

class TaggedIteratorArgumentTest extends TestCase
{
    public function testWithTagOnly()
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo');

        self::assertSame('foo', $taggedIteratorArgument->getTag());
        self::assertNull($taggedIteratorArgument->getIndexAttribute());
        self::assertNull($taggedIteratorArgument->getDefaultIndexMethod());
        self::assertFalse($taggedIteratorArgument->needsIndexes());
        self::assertNull($taggedIteratorArgument->getDefaultPriorityMethod());
    }

    public function testOnlyTagWithNeedsIndexes()
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo', null, null, true);

        self::assertSame('foo', $taggedIteratorArgument->getTag());
        self::assertSame('foo', $taggedIteratorArgument->getIndexAttribute());
        self::assertSame('getDefaultFooName', $taggedIteratorArgument->getDefaultIndexMethod());
        self::assertSame('getDefaultFooPriority', $taggedIteratorArgument->getDefaultPriorityMethod());
    }

    public function testOnlyTagWithNeedsIndexesAndDotTag()
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo.bar', null, null, true);

        self::assertSame('foo.bar', $taggedIteratorArgument->getTag());
        self::assertSame('bar', $taggedIteratorArgument->getIndexAttribute());
        self::assertSame('getDefaultBarName', $taggedIteratorArgument->getDefaultIndexMethod());
        self::assertSame('getDefaultBarPriority', $taggedIteratorArgument->getDefaultPriorityMethod());
    }

    public function testOnlyTagWithNeedsIndexesAndDotsTag()
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo.bar.baz.qux', null, null, true);

        self::assertSame('foo.bar.baz.qux', $taggedIteratorArgument->getTag());
        self::assertSame('qux', $taggedIteratorArgument->getIndexAttribute());
        self::assertSame('getDefaultQuxName', $taggedIteratorArgument->getDefaultIndexMethod());
        self::assertSame('getDefaultQuxPriority', $taggedIteratorArgument->getDefaultPriorityMethod());
    }

    /**
     * @dataProvider defaultIndexMethodProvider
     */
    public function testDefaultIndexMethod(?string $indexAttribute, ?string $defaultIndexMethod, ?string $expectedDefaultIndexMethod)
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo', $indexAttribute, $defaultIndexMethod);

        self::assertSame($expectedDefaultIndexMethod, $taggedIteratorArgument->getDefaultIndexMethod());
    }

    public function defaultIndexMethodProvider()
    {
        yield 'No indexAttribute and no defaultIndexMethod' => [
            null,
            null,
            null,
        ];

        yield 'Only indexAttribute' => [
            'bar',
            null,
            'getDefaultBarName',
        ];

        yield 'Only defaultIndexMethod' => [
            null,
            'getBaz',
            'getBaz',
        ];

        yield 'DefaultIndexMethod and indexAttribute' => [
            'bar',
            'getBaz',
            'getBaz',
        ];

        yield 'Transform to getter with one special char' => [
            'bar_baz',
            null,
            'getDefaultBarBazName',
        ];

        yield 'Transform to getter with multiple special char' => [
            'bar-baz-qux',
            null,
            'getDefaultBarBazQuxName',
        ];
    }

    /**
     * @dataProvider defaultPriorityMethodProvider
     */
    public function testDefaultPriorityIndexMethod(?string $indexAttribute, ?string $defaultPriorityMethod, ?string $expectedDefaultPriorityMethod)
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo', $indexAttribute, null, false, $defaultPriorityMethod);

        self::assertSame($expectedDefaultPriorityMethod, $taggedIteratorArgument->getDefaultPriorityMethod());
    }

    public function defaultPriorityMethodProvider()
    {
        yield 'No indexAttribute and no defaultPriorityMethod' => [
            null,
            null,
            null,
        ];

        yield 'Only indexAttribute' => [
            'bar',
            null,
            'getDefaultBarPriority',
        ];

        yield 'Only defaultPriorityMethod' => [
            null,
            'getBaz',
            'getBaz',
        ];

        yield 'DefaultPriorityMethod and indexAttribute' => [
            'bar',
            'getBaz',
            'getBaz',
        ];

        yield 'Transform to getter with one special char' => [
            'bar_baz',
            null,
            'getDefaultBarBazPriority',
        ];

        yield 'Transform to getter with multiple special char' => [
            'bar-baz-qux',
            null,
            'getDefaultBarBazQuxPriority',
        ];
    }
}
