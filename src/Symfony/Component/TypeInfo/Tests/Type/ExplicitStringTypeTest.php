<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Type;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type\ExplicitStringType;

class ExplicitStringTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('class-string', (string) new ExplicitStringType('class-string'));
    }

    public function testAccepts()
    {
        $this->assertFalse((new ExplicitStringType('interface-string'))->accepts(false));
        $this->assertTrue((new ExplicitStringType('interface-string'))->accepts('Foo'));
    }
}
