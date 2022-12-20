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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class DiscriminatorMapTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @requires PHP 8
     */
    public function testGetTypePropertyAndMapping()
    {
        $annotation = new DiscriminatorMap(...['typeProperty' => 'type', 'mapping' => [
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ]]);

        self::assertEquals('type', $annotation->getTypeProperty());
        self::assertEquals([
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ], $annotation->getMapping());
    }

    /**
     * @group legacy
     */
    public function testGetTypePropertyAndMappingLegacy()
    {
        $this->expectDeprecation('Since symfony/serializer 5.3: Passing an array as first argument to "Symfony\Component\Serializer\Annotation\DiscriminatorMap::__construct" is deprecated. Use named arguments instead.');
        $annotation = new DiscriminatorMap(['typeProperty' => 'type', 'mapping' => [
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ]]);

        self::assertEquals('type', $annotation->getTypeProperty());
        self::assertEquals([
            'foo' => 'FooClass',
            'bar' => 'BarClass',
        ], $annotation->getMapping());
    }

    /**
     * @group legacy
     */
    public function testExceptionWithoutTypeProperty()
    {
        self::expectException(InvalidArgumentException::class);
        new DiscriminatorMap(['mapping' => ['foo' => 'FooClass']]);
    }

    /**
     * @requires PHP 8
     */
    public function testExceptionWithEmptyTypeProperty()
    {
        self::expectException(InvalidArgumentException::class);
        new DiscriminatorMap(...['typeProperty' => '', 'mapping' => ['foo' => 'FooClass']]);
    }

    /**
     * @group legacy
     */
    public function testExceptionWithEmptyTypePropertyLegacy()
    {
        self::expectException(InvalidArgumentException::class);
        new DiscriminatorMap(['typeProperty' => '', 'mapping' => ['foo' => 'FooClass']]);
    }

    /**
     * @requires PHP 8
     */
    public function testExceptionWithoutMappingProperty()
    {
        self::expectException(InvalidArgumentException::class);
        new DiscriminatorMap(...['typeProperty' => 'type']);
    }

    /**
     * @group legacy
     */
    public function testExceptionWithoutMappingPropertyLegacy()
    {
        self::expectException(InvalidArgumentException::class);
        new DiscriminatorMap(['typeProperty' => 'type']);
    }

    /**
     * @requires PHP 8
     */
    public function testExceptionWitEmptyMappingProperty()
    {
        self::expectException(InvalidArgumentException::class);
        new DiscriminatorMap(...['typeProperty' => 'type', 'mapping' => []]);
    }

    /**
     * @group legacy
     */
    public function testExceptionWitEmptyMappingPropertyLegacy()
    {
        self::expectException(InvalidArgumentException::class);
        new DiscriminatorMap(['typeProperty' => 'type', 'mapping' => []]);
    }
}
