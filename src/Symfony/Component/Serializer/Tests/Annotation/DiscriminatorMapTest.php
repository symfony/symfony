<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class DiscriminatorMapTest extends TestCase
{
    public function testGetTypePropertyAndMapping()
    {
        $annotation = new DiscriminatorMap(typeProperty: 'type', mapping: [
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ]);

        $this->assertEquals('type', $annotation->getTypeProperty());
        $this->assertEquals([
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ], $annotation->getMapping());
    }

    public function testExceptionWithEmptyTypeProperty()
    {
        $this->expectException(InvalidArgumentException::class);
        new DiscriminatorMap(typeProperty: '', mapping: ['foo' => 'FooClass']);
    }

    public function testExceptionWitEmptyMappingProperty()
    {
        $this->expectException(InvalidArgumentException::class);
        new DiscriminatorMap(typeProperty: 'type', mapping: []);
    }
}
