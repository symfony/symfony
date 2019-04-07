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
use Symfony\Component\Serializer\Extractor\IgnoredPropertyListExtractor;

class IgnoredPropertyListExtractorTest extends TestCase
{
    public function testRemoveAttributes()
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

        $ignored = new IgnoredPropertyListExtractor($extractor);
        $properties = $ignored->getProperties('SomeClass');

        $this->assertInternalType('array', $properties);
        $this->assertContains('foo', $properties);
        $this->assertContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $properties = $ignored->getProperties('SomeClass', [
            IgnoredPropertyListExtractor::ATTRIBUTES => ['foo', 'bar', 'dummy'],
        ]);

        $this->assertInternalType('array', $properties);
        $this->assertNotContains('foo', $properties);
        $this->assertNotContains('bar', $properties);
        $this->assertNotContains('dummy', $properties);
        $this->assertContains('baz', $properties);
    }

    public function testNullProperties()
    {
        $extractor = $this
            ->getMockBuilder(PropertyListExtractorInterface::class)
            ->getMock()
        ;

        $extractor->method('getProperties')->willReturn(null);

        $ignored = new IgnoredPropertyListExtractor($extractor);
        $properties = $ignored->getProperties('SomeClass');

        $this->assertNull($properties);
    }
}
