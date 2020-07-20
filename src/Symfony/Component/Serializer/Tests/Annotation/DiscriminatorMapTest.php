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

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class DiscriminatorMapTest extends TestCase
{
    public function testGetTypePropertyAndMapping()
    {
        $annotation = new DiscriminatorMap(['typeProperty' => 'type', 'mapping' => [
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ]]);

        $this->assertEquals('type', $annotation->getTypeProperty());
        $this->assertEquals([
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ], $annotation->getMapping());
    }

    public function testExceptionWithoutTypeProperty()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        new DiscriminatorMap(['mapping' => ['foo' => 'FooClass']]);
    }

    public function testExceptionWithEmptyTypeProperty()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        new DiscriminatorMap(['typeProperty' => '', 'mapping' => ['foo' => 'FooClass']]);
    }

    public function testExceptionWithoutMappingProperty()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        new DiscriminatorMap(['typeProperty' => 'type']);
    }

    public function testExceptionWitEmptyMappingProperty()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        new DiscriminatorMap(['typeProperty' => 'type', 'mapping' => []]);
    }
}
