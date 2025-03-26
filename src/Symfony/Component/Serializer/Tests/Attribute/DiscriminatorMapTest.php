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
    public function testGetTypePropertyAndMapping()
    {
        $attribute = new DiscriminatorMap(typeProperty: 'type', mapping: [
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ]);

        $this->assertEquals('type', $attribute->getTypeProperty());
        $this->assertEquals([
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ], $attribute->getMapping());
    }

    public function testExceptionWithEmptyTypeProperty()
    {
        $this->expectException(InvalidArgumentException::class);
        new DiscriminatorMap(typeProperty: '', mapping: ['foo' => 'FooClass']);
    }

    public function testExceptionWithEmptyMappingProperty()
    {
        $this->expectException(InvalidArgumentException::class);
        new DiscriminatorMap(typeProperty: 'type', mapping: []);
    }

    public function testExceptionWithMissingDefaultTypeInMapping()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Default type "bar" given to "%s" must be present in "mapping" types.', DiscriminatorMap::class));
        new DiscriminatorMap(typeProperty: 'type', mapping: ['foo' => 'FooClass'], defaultType: 'bar');
    }
}
