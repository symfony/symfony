<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Serializer\Context\ChildContextBuilderInterface;
use Symfony\Component\Serializer\Extractor\AllowedPropertyListExtractor;

class AllowedPropertyListExtractorTest extends TestCase
{
    public function testAllowAttributes()
    {
        $extractor = $this
            ->getMockBuilder(PropertyListExtractorInterface::class)
            ->getMock()
        ;

        $extractor->method('getProperties')->willReturn([
            'foo',
            'bar',
            'baz'
        ]);

        $allowed = new AllowedPropertyListExtractor($extractor);
        $properties = $allowed->getProperties('SomeClass');

        $this->assertInternalType('array', $properties);
        $this->assertContains('foo', $properties);
        $this->assertContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $properties = $allowed->getProperties('SomeClass', [
            AllowedPropertyListExtractor::ATTRIBUTES => ['foo', 'bar', 'dummy'],
        ]);

        $this->assertInternalType('array', $properties);
        $this->assertContains('foo', $properties);
        $this->assertContains('bar', $properties);
        $this->assertNotContains('dummy', $properties);
        $this->assertNotContains('baz', $properties);

        $properties = $allowed->getProperties('SomeClass', [
            AllowedPropertyListExtractor::ATTRIBUTES => [],
        ]);

        $this->assertInternalType('array', $properties);
        $this->assertEmpty($properties);
    }

    public function testNullProperties()
    {
        $extractor = $this
            ->getMockBuilder(PropertyListExtractorInterface::class)
            ->getMock()
        ;

        $extractor->method('getProperties')->willReturn(null);

        $allowed = new AllowedPropertyListExtractor($extractor);
        $properties = $allowed->getProperties('SomeClass');

        $this->assertNull($properties);
    }

    public function testDecorateChildContext()
    {
        $extractor = $this
            ->getMockBuilder([PropertyListExtractorInterface::class, ChildContextBuilderInterface::class])
            ->getMock()
        ;

        $extractor->method('createChildContextForAttribute')->willReturn([]);

        $allowed = new AllowedPropertyListExtractor($extractor);
        $childContext = $allowed->createChildContextForAttribute(['test'], 'some_attribute');

        $this->assertSame([], $childContext);
    }

    public function testCreateAttributesForNested()
    {
        $extractor = $this
            ->getMockBuilder([PropertyListExtractorInterface::class, ChildContextBuilderInterface::class])
            ->getMock()
        ;

        $extractor->method('createChildContextForAttribute')->willReturnArgument(0);

        $allowed = new AllowedPropertyListExtractor($extractor);
        $childContext = $allowed->createChildContextForAttribute([
            AllowedPropertyListExtractor::ATTRIBUTES => [
                'foo',
                'bar' => [
                    'baz'
                ]
            ],
        ], 'foo');

        $this->assertSame([], $childContext);

        $childContext = $allowed->createChildContextForAttribute([
            AllowedPropertyListExtractor::ATTRIBUTES => [
                'foo',
                'bar' => [
                    'baz'
                ]
            ],
        ], 'bar');

        $this->assertSame([AllowedPropertyListExtractor::ATTRIBUTES => ['baz']], $childContext);
    }
}
