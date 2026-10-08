<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\NullSyntax;

class DialectTest extends TestCase
{
    public function testJsonSchema202012Keywords()
    {
        $dialect = Dialect::jsonSchema202012();

        $this->assertSame(['const' => 'draft'], $dialect->const('draft'));
        $this->assertSame(['exclusiveMinimum' => 0], $dialect->exclusiveMinimum(0));
        $this->assertSame(['exclusiveMaximum' => 1.5], $dialect->exclusiveMaximum(1.5));
        $this->assertSame(['examples' => ['jane@example.com']], $dialect->example('jane@example.com'));
    }

    public function testOpenApi30Keywords()
    {
        $dialect = Dialect::openApi30();

        $this->assertSame(['enum' => ['draft']], $dialect->const('draft'));
        $this->assertSame(['minimum' => 0, 'exclusiveMinimum' => true], $dialect->exclusiveMinimum(0));
        $this->assertSame(['maximum' => 1.5, 'exclusiveMaximum' => true], $dialect->exclusiveMaximum(1.5));
        $this->assertSame(['example' => 'jane@example.com'], $dialect->example('jane@example.com'));
    }

    public function testReferenceSiblingsSupport()
    {
        $this->assertTrue(Dialect::jsonSchema202012()->supportsRefSiblings);
        $this->assertTrue(Dialect::openApi31()->supportsRefSiblings);
        $this->assertFalse(Dialect::openApi30()->supportsRefSiblings);
        $this->assertFalse(Dialect::swagger20()->supportsRefSiblings);
    }

    public function testNumericExclusiveBoundsFlagDrivesBothBounds()
    {
        $dialect = new Dialect('#/$defs/', NullSyntax::Union, supportsNumericExclusiveBounds: false);

        $this->assertSame(['minimum' => 1, 'exclusiveMinimum' => true], $dialect->exclusiveMinimum(1));
        $this->assertSame(['maximum' => 9, 'exclusiveMaximum' => true], $dialect->exclusiveMaximum(9));
    }
}
