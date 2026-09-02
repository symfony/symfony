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
use Symfony\Component\Serializer\Attribute\DiscriminatorMapType;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

class DiscriminatorMapTypeTest extends TestCase
{
    public function testProperties()
    {
        $attribute = new DiscriminatorMapType('email', self::class);

        $this->assertSame('email', $attribute->type);
        $this->assertSame(self::class, $attribute->class);
    }

    public function testRejectsEmptyType()
    {
        $this->expectException(InvalidArgumentException::class);
        new DiscriminatorMapType('', self::class);
    }

    public function testRejectsEmptyClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter "class"');
        new DiscriminatorMapType('email', '');
    }
}
