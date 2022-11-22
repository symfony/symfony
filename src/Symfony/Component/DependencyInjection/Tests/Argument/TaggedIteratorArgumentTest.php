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

        $this->assertSame('foo', $taggedIteratorArgument->getTag());
        $this->assertNull($taggedIteratorArgument->getIndexAttribute());
        $this->assertNull($taggedIteratorArgument->getDefaultIndexMethod());
        $this->assertFalse($taggedIteratorArgument->needsIndexes());
        $this->assertNull($taggedIteratorArgument->getDefaultPriorityMethod());
    }

    public function testOnlyTagWithNeedsIndexes()
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo', null, null, true);

        $this->assertSame('foo', $taggedIteratorArgument->getTag());
        $this->assertSame('foo', $taggedIteratorArgument->getIndexAttribute());
        $this->assertSame('getDefaultFooName', $taggedIteratorArgument->getDefaultIndexMethod());
        $this->assertSame('getDefaultFooPriority', $taggedIteratorArgument->getDefaultPriorityMethod());
    }

    public function testOnlyTagWithNeedsIndexesAndDotTag()
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo.bar', null, null, true);

        $this->assertSame('foo.bar', $taggedIteratorArgument->getTag());
        $this->assertSame('bar', $taggedIteratorArgument->getIndexAttribute());
        $this->assertSame('getDefaultBarName', $taggedIteratorArgument->getDefaultIndexMethod());
        $this->assertSame('getDefaultBarPriority', $taggedIteratorArgument->getDefaultPriorityMethod());
    }

    public function testOnlyTagWithNeedsIndexesAndDotsTag()
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo.bar.baz.qux', null, null, true);

        $this->assertSame('foo.bar.baz.qux', $taggedIteratorArgument->getTag());
        $this->assertSame('qux', $taggedIteratorArgument->getIndexAttribute());
        $this->assertSame('getDefaultQuxName', $taggedIteratorArgument->getDefaultIndexMethod());
        $this->assertSame('getDefaultQuxPriority', $taggedIteratorArgument->getDefaultPriorityMethod());
    }

    /**
     * @dataProvider defaultIndexMethodProvider
     */
    public function testDefaultIndexMethod(?string $indexAttribute, ?string $defaultIndexMethod, ?string $expectedDefaultIndexMethod)
    {
        $taggedIteratorArgument = new TaggedIteratorArgument('foo', $indexAttribute, $defaultIndexMethod);

        $this->assertSame($expectedDefaultIndexMethod, $taggedIteratorArgument->getDefaultIndexMethod());
    }

    public static function defaultIndexMethodProvider()
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

        $this->assertSame($expectedDefaultPriorityMethod, $taggedIteratorArgument->getDefaultPriorityMethod());
    }

    public static function defaultPriorityMethodProvider()
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
