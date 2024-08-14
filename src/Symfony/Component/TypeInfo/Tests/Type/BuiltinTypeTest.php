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
use Symfony\Component\TypeInfo\TypeIdentifier;

class BuiltinTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('int', (string) new BuiltinType(TypeIdentifier::INT));
    }

    public function testIsIdentifiedBy()
    {
        $this->assertFalse((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy(TypeIdentifier::ARRAY));
        $this->assertTrue((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy(TypeIdentifier::INT));

        $this->assertFalse((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy('array'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy('int'));

        $this->assertTrue((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy('string', 'int'));
    }

    public function testIsNullable()
    {
        $this->assertTrue((new BuiltinType(TypeIdentifier::NULL))->isNullable());
        $this->assertTrue((new BuiltinType(TypeIdentifier::MIXED))->isNullable());
        $this->assertFalse((new BuiltinType(TypeIdentifier::INT))->isNullable());
    }
}
