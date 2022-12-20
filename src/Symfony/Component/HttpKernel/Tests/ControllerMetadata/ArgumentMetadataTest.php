<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\ControllerMetadata;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\HttpKernel\Attribute\ArgumentInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Foo;

class ArgumentMetadataTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testWithBcLayerWithDefault()
    {
        $argument = new ArgumentMetadata('foo', 'string', false, true, 'default value');

        self::assertFalse($argument->isNullable());
    }

    public function testDefaultValueAvailable()
    {
        $argument = new ArgumentMetadata('foo', 'string', false, true, 'default value', true);

        self::assertTrue($argument->isNullable());
        self::assertTrue($argument->hasDefaultValue());
        self::assertSame('default value', $argument->getDefaultValue());
    }

    public function testDefaultValueUnavailable()
    {
        self::expectException(\LogicException::class);
        $argument = new ArgumentMetadata('foo', 'string', false, false, null, false);

        self::assertFalse($argument->isNullable());
        self::assertFalse($argument->hasDefaultValue());
        $argument->getDefaultValue();
    }

    /**
     * @group legacy
     */
    public function testLegacyAttribute()
    {
        $attribute = self::createMock(ArgumentInterface::class);

        $this->expectDeprecation('Since symfony/http-kernel 5.3: The "Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata" constructor expects an array of PHP attributes as last argument, %s given.');
        $this->expectDeprecation('Since symfony/http-kernel 5.3: Method "Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata::getAttribute()" is deprecated, use "getAttributes()" instead.');

        $argument = new ArgumentMetadata('foo', 'string', false, true, 'default value', true, $attribute);
        self::assertSame($attribute, $argument->getAttribute());
    }

    /**
     * @requires PHP 8
     */
    public function testGetAttributes()
    {
        $argument = new ArgumentMetadata('foo', 'string', false, true, 'default value', true, [new Foo('bar')]);
        self::assertEquals([new Foo('bar')], $argument->getAttributes());
    }
}
