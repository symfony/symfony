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
}
