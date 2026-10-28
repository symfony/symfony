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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\DialectKeywords;

class DialectKeywordsTest extends TestCase
{
    #[DataProvider('provideSchemas')]
    public function testEnumKeepsNullWhenTheSchemaAllowsIt(array $schema, array $expectedEnum)
    {
        $this->assertSame($expectedEnum, DialectKeywords::enum($schema, ['a', 'b'])['enum']);
    }

    public static function provideSchemas(): iterable
    {
        yield 'not nullable' => [['type' => 'string'], ['a', 'b']];
        yield 'type union' => [['type' => ['string', 'null']], ['a', 'b', null]];
        yield 'nullable flag' => [['type' => 'string', 'nullable' => true], ['a', 'b', null]];
        yield 'anyOf branch' => [['anyOf' => [['$ref' => '#/$defs/Code'], ['type' => 'null']]], ['a', 'b', null]];
        yield 'true anyOf branch' => [['anyOf' => [['type' => 'string'], true]], ['a', 'b', null]];
        yield 'false anyOf branch' => [['anyOf' => [['type' => 'string'], false]], ['a', 'b']];
    }
}
