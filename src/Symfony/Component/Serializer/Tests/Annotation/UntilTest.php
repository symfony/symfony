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
use Symfony\Component\Serializer\Annotation\Until;

/**
 * @author Arnaud Tarroux <ta.arnaud@gmail.com>
 */
class UntilTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage Parameter of annotation "Symfony\Component\Serializer\Annotation\Until" should be set.
     */
    public function testNotSetVersionParameter()
    {
        new Until([]);
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
     *
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage Parameter of annotation "Symfony\Component\Serializer\Annotation\Until" must be a non-empty string.
     */
    public function testNotAStringVersionParameter($value)
    {
        new Until(['value' => $value]);
    }

    public function testVersionParameters()
    {
        $since = new Until(['value' => '1.1.2']);
        $this->assertEquals('1.1.2', $since->getVersion());
    }
}
