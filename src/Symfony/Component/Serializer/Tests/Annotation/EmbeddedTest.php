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
use Symfony\Component\Serializer\Annotation\Embedded;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Jibé Barth <barth.jib@gmail.com>
 */
class EmbeddedTest extends TestCase
{
    public function testEmbeddedConstructor()
    {
        $embedded = new Embedded(['value' => []]);
        $this->assertInstanceOf(Embedded::class, $embedded);
    }

    public function testEmbeddedEmptyParameters()
    {
        $embedded = new Embedded([]);
        $this->assertInstanceOf(Embedded::class, $embedded);
    }

    public function testNotEmptyArrayEmbeddedParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        new Embedded(['value' => 12]);
    }

    public function testInvalidEmbeddedParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        new Embedded(['value' => ['a', 1, new \stdClass()]]);
    }
}
