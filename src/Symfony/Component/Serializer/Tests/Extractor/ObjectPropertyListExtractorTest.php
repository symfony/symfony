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
use Symfony\Component\Serializer\Extractor\ObjectPropertyListExtractor;

class ObjectPropertyListExtractorTest extends TestCase
{
    public function testGetPropertiesWithoutObjectClassResolver()
    {
        $object = new \stdClass();
        $context = ['bar' => true];
        $properties = ['prop1', 'prop2'];

        $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
        $propertyListExtractor->expects($this->once())
            ->method('getProperties')
            ->with($object::class, $context)
            ->willReturn($properties);

        $this->assertSame(
            $properties,
            (new ObjectPropertyListExtractor($propertyListExtractor))->getProperties($object, $context)
        );
    }

    public function testGetPropertiesWithObjectClassResolver()
    {
        $object = new \stdClass();
        $classResolver = function ($objectArg) use ($object): string {
            $this->assertSame($object, $objectArg);

            return 'foo';
        };

        $context = ['bar' => true];
        $properties = ['prop1', 'prop2'];

        $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
        $propertyListExtractor->expects($this->once())
            ->method('getProperties')
            ->with('foo', $context)
            ->willReturn($properties);

        $this->assertSame(
            $properties,
            (new ObjectPropertyListExtractor($propertyListExtractor, $classResolver))->getProperties($object, $context)
        );
    }
}
