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
use Symfony\Component\Serializer\Annotation\Since;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Arnaud Tarroux <ta.arnaud@gmail.com>
 */
class SinceTest extends TestCase
{
    public function testNotSetVersionParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Parameter of annotation "Symfony\Component\Serializer\Annotation\Since" should be set.'
        );
        new Since([]);
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
    public function testNotAStringVersionParameter($value)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Parameter of annotation "Symfony\Component\Serializer\Annotation\Since" must be a non-empty string.'
        );
        new Since(['value' => $value]);
    }

    public function testVersionParameters()
    {
        $since = new Since(['value' => '1.1.2']);
        $this->assertSame('1.1.2', $since->getVersion());
    }
}
