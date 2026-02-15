<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\ArrayShapeGenerator;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;
use Symfony\Component\Config\Definition\StringNode;
use Symfony\Component\Config\Definition\VariableNode;
use Symfony\Component\Config\Tests\Fixtures\IntegerBackedTestEnum;
use Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum;

class ArrayShapeGeneratorTest extends TestCase
{
    #[DataProvider('provideNodes')]
    public function testPhpDocHandlesNodeTypes(NodeInterface $node, string $expected)
    {
        $arrayNode = new ArrayNode('root');
        $arrayNode->addChild($node);

        $expected = 'node?: '.$expected;

        $this->assertStringContainsString($expected, ArrayShapeGenerator::generate($arrayNode));
    }

    public static function provideNodes(): iterable
    {
        yield [new ArrayNode('node'), 'array<mixed>'];

        yield [new StringNode('node'), 'string'];

        yield [new BooleanNode('node'), 'bool'];

        $nullableBooleanNode = new BooleanNode('node');
        $nullableBooleanNode->setDefaultValue(null);

        yield [$nullableBooleanNode, 'bool|\Symfony\Component\Config\Loader\ParamConfigurator|null'];
        yield [new EnumNode('node', values: ['a', 'b']), '"a"|"b"'];
        yield [new EnumNode('node', enumFqcn: StringBackedTestEnum::class), 'value-of<\Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum>|\Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum'];
        yield [new EnumNode('node', enumFqcn: IntegerBackedTestEnum::class), 'value-of<\Symfony\Component\Config\Tests\Fixtures\IntegerBackedTestEnum>|\Symfony\Component\Config\Tests\Fixtures\IntegerBackedTestEnum'];
        yield [new ScalarNode('node'), 'scalar|\Symfony\Component\Config\Loader\ParamConfigurator|null'];
        yield [new VariableNode('node'), 'mixed'];

        yield [new IntegerNode('node'), 'int'];
        yield [new IntegerNode('node', min: 1), 'int'];
        yield [new IntegerNode('node', max: 10), 'int'];
        yield [new IntegerNode('node', min: 1, max: 10), 'int'];

        yield [new FloatNode('node'), 'float'];
        yield [new FloatNode('node', min: 1.1), 'float'];
        yield [new FloatNode('node', max: 10.1), 'float'];
        yield [new FloatNode('node', min: 1.1, max: 10.1), 'float'];
    }

    public function testPrototypedArrayNodePhpDoc()
    {
        $prototype = new PrototypedArrayNode('proto');
        $prototype->setPrototype(new StringNode('child'));

        $root = new ArrayNode('root');
        $root->addChild($prototype);

        $expected = "array{\n *     proto?: list<string|\Symfony\Component\Config\Loader\ParamConfigurator>,\n * }";

        $this->assertStringContainsString($expected, ArrayShapeGenerator::generate($root));
    }

    public function testPrototypedArrayNodePhpDocWithKeyAttribute()
    {
        $prototype = new PrototypedArrayNode('proto');
        $prototype->setPrototype(new StringNode('child'));
        $prototype->setKeyAttribute('name');

        $root = new ArrayNode('root');
        $root->addChild($prototype);

        $expected = "array{\n *     proto?: array<string, string|\Symfony\Component\Config\Loader\ParamConfigurator>,\n * }";

        $this->assertStringContainsString($expected, ArrayShapeGenerator::generate($root));
    }

    public function testPhpDocHandlesRequiredNode()
    {
        $child = new BooleanNode('node');
        $child->setRequired(true);

        $root = new ArrayNode('root');
        $root->addChild($child);

        $expected = 'node: bool';

        $this->assertStringContainsString($expected, ArrayShapeGenerator::generate($root));
    }

    public function testPhpDocHandleAdditionalDocumentation()
    {
        $child = new BooleanNode('node');
        $child->setDeprecated('vendor/package', '1.0', 'The "%path%" option is deprecated.');
        $child->setDefaultValue(true);
        $child->setInfo('This is a boolean node.');

        $root = new ArrayNode('root');
        $root->addChild($child);

        $this->assertStringContainsString('node?: bool|\Symfony\Component\Config\Loader\ParamConfigurator, // Deprecated: The "node" option is deprecated. // This is a boolean node. // Default: true', ArrayShapeGenerator::generate($root));
    }

    public function testPhpDocHandleMultilineDoc()
    {
        $child = new BooleanNode('node');
        $child->setDeprecated('vendor/package', '1.0', 'The "%path%" option is deprecated.');
        $child->setDefaultValue(true);
        $child->setInfo("This is a boolean node.\nSet to true to enable it.\r\nSet to false to disable it.");

        $root = new ArrayNode('root');
        $root->addChild($child);

        $this->assertStringContainsString('node?: bool|\Symfony\Component\Config\Loader\ParamConfigurator, // Deprecated: The "node" option is deprecated. // This is a boolean node. Set to true to enable it. Set to false to disable it. // Default: true', ArrayShapeGenerator::generate($root));
    }

    public function testPhpDocShapeSingleLevel()
    {
        $root = new ArrayNode('root');

        $this->assertStringMatchesFormat('array<%s>', ArrayShapeGenerator::generate($root));
    }

    public function testPhpDocShapeMultiLevel()
    {
        $root = new ArrayNode('root');
        $child = new ArrayNode('child');
        $root->addChild($child);

        $this->assertStringMatchesFormat('array{%Achild?: array<%s>,%A}', ArrayShapeGenerator::generate($root));
    }

    public function testCanBeEnabled()
    {
        $root = new ArrayNodeDefinition('root');
        $root->canBeEnabled();

        $this->assertSame(<<<'CODE'
            bool|array{
             *     enabled?: bool|\Symfony\Component\Config\Loader\ParamConfigurator, // Default: false
             * }
            CODE, ArrayShapeGenerator::generate($root->getNode()));
    }

    public function testCanBeDisabled()
    {
        $root = new ArrayNodeDefinition('root');
        $root->canBeDisabled();

        $this->assertSame(<<<'CODE'
            bool|array{
             *     enabled?: bool|\Symfony\Component\Config\Loader\ParamConfigurator, // Default: true
             * }
            CODE, ArrayShapeGenerator::generate($root->getNode()));
    }

    #[DataProvider('provideQuotedNodes')]
    public function testPhpdocQuoteNodeName(NodeInterface $node, string $expected)
    {
        $arrayNode = new ArrayNode('root');
        $arrayNode->addChild($node);

        $this->assertStringContainsString($expected, ArrayShapeGenerator::generate($arrayNode));
    }

    public static function provideQuotedNodes(): \Generator
    {
        yield [new StringNode('int'), "'int'"];
        yield [new StringNode('float'), "'float'"];
        yield [new StringNode('null'), "'null'"];
        yield [new StringNode('bool'), "'bool'"];
        yield [new StringNode('scalar'), "'scalar'"];
        yield [new StringNode('hell"o'), "'hell\\\"o'"];
        yield [new StringNode("hell'o"), "'hell\\'o'"];
        yield [new StringNode('@key'), "'@key'"];
    }
}
