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
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
class SerializedNameTest extends TestCase
{
    public function testNotSetSerializedNameParameter()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\SerializedName" should be set.');
        new SerializedName([]);
    }

    public function provideInvalidValues()
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
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\SerializedName" must be a non-empty string.');
        new SerializedName(['value' => $value]);
    }

    public function testSerializedNameParameters()
    {
        $maxDepth = new SerializedName(['value' => 'foo']);
        $this->assertEquals('foo', $maxDepth->getSerializedName());
    }
}
