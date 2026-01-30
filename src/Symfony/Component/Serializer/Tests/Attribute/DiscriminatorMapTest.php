<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class DiscriminatorMapTest extends TestCase
{
    public function testGetTypePropertyAndMapping(): void
    {
        $attribute = new DiscriminatorMap(typeProperty: 'type', mapping: [
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ]);

        $this->assertEquals('type', $attribute->typeProperty);
        $this->assertEquals([
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ], $attribute->mapping);
    }

    public function testExceptionWithEmptyTypeProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DiscriminatorMap(typeProperty: '', mapping: ['foo' => 'FooClass']);
    }

    public function testExceptionWithEmptyMappingProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DiscriminatorMap(typeProperty: 'type', mapping: []);
    }

    public function testExceptionWithMissingDefaultTypeInMapping(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Default type "bar" given to "%s" must be present in "mapping" types.', DiscriminatorMap::class));
        new DiscriminatorMap(typeProperty: 'type', mapping: ['foo' => 'FooClass'], defaultType: 'bar');
    }
}
