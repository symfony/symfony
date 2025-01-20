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
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class TemplateTypeTest extends TestCase
{
    public function testAccepts()
    {
        $this->assertFalse((new TemplateType('T', new BuiltinType(TypeIdentifier::BOOL)))->accepts('string'));
        $this->assertTrue((new TemplateType('T', new BuiltinType(TypeIdentifier::BOOL)))->accepts(true));
    }
}
