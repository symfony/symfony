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
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
class SerializedNameTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testNotSetSerializedNameParameter()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\SerializedName" should be set.');
        new SerializedName([]);
    }

    public function provideInvalidValues(): array
    {
        return [
            [''],
            [0],
        ];
    }

    /**
     * @dataProvider provideInvalidValues
     */
    public function testNotAStringSerializedNameParameter($value)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\SerializedName" must be a non-empty string.');

        new SerializedName($value);
    }

    public function testSerializedNameParameters()
    {
        $maxDepth = new SerializedName('foo');
        self::assertEquals('foo', $maxDepth->getSerializedName());
    }

    /**
     * @group legacy
     */
    public function testSerializedNameParametersLegacy()
    {
        $this->expectDeprecation('Since symfony/serializer 5.3: Passing an array as first argument to "Symfony\Component\Serializer\Annotation\SerializedName::__construct" is deprecated. Use named arguments instead.');

        $maxDepth = new SerializedName(['value' => 'foo']);
        self::assertEquals('foo', $maxDepth->getSerializedName());
    }
}
