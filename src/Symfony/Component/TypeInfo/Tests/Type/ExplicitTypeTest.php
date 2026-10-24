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
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\ExplicitType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class ExplicitTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('non-empty-string', (string) new ExplicitType(TypeIdentifier::STRING, 'non-empty-string'));
    }

    public function testIsBuiltinType()
    {
        $type = new ExplicitType(TypeIdentifier::STRING, 'non-empty-string');

        $this->assertInstanceOf(BuiltinType::class, $type);
        $this->assertSame(TypeIdentifier::STRING, $type->getTypeIdentifier());
        $this->assertEquals(new BuiltinType(TypeIdentifier::STRING), $type->getBuiltinType());
    }

    public function testIsIdentifiedBy()
    {
        $this->assertFalse((new ExplicitType(TypeIdentifier::STRING, 'non-empty-string'))->isIdentifiedBy(TypeIdentifier::INT));
        $this->assertTrue((new ExplicitType(TypeIdentifier::STRING, 'non-empty-string'))->isIdentifiedBy(TypeIdentifier::STRING));
        $this->assertTrue((new ExplicitType(TypeIdentifier::STRING, 'non-empty-string'))->isIdentifiedBy('string'));
    }

    public function testAccepts()
    {
        $this->assertFalse((new ExplicitType(TypeIdentifier::STRING, 'non-empty-string'))->accepts(123));
        $this->assertTrue((new ExplicitType(TypeIdentifier::STRING, 'non-empty-string'))->accepts('string'));
    }
}
