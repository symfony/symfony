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
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\NodeInterface;

class BaseNodeTest extends TestCase
{
    #[DataProvider('providePath')]
    public function testGetPathForChildNode(string $expected, array $params)
    {
        $constructorArgs = [];
        $constructorArgs[] = $params[0];

        if (isset($params[1])) {
            $parent = $this->createStub(NodeInterface::class);
            $parent->method('getPath')->willReturn($params[1]);

            $constructorArgs[] = $parent;

            if (isset($params[2])) {
                $constructorArgs[] = $params[2];
            }
        }

        $node = new class(...$constructorArgs) extends BaseNode {
            protected function validateType($value): void
            {
            }

            protected function normalizeValue($value): mixed
            {
                return null;
            }

            protected function mergeValues($leftSide, $rightSide): mixed
            {
                return null;
            }

            protected function finalizeValue($value): mixed
            {
                return null;
            }

            public function hasDefaultValue(): bool
            {
                return true;
            }

            public function getDefaultValue(): mixed
            {
                return null;
            }
        };

        $this->assertSame($expected, $node->getPath());
    }

    public static function providePath(): array
    {
        return [
            'name only' => ['root', ['root']],
            'name and parent' => ['foo.bar.baz.bim', ['bim', 'foo.bar.baz']],
            'name and separator' => ['foo', ['foo', null, '/']],
            'name, parent and separator' => ['foo.bar/baz/bim', ['bim', 'foo.bar/baz', '/']],
        ];
    }

    public function testEnvVarsAreInlined()
    {
        $tree = (new TreeBuilder('root'))
            ->getRootNode()
                ->children()
                    ->scalarNode('static')->inlineEnvVars()->end()
                    ->scalarNode('dynamic')->end()
                ->end()
            ->end()
            ->buildTree()
        ;

        BaseNode::setPlaceholderUniquePrefix('env_test');
        BaseNode::setPlaceholderResolver(static fn (mixed $value): mixed => 'env_test_FOO' === $value ? 'resolved' : $value);

        try {
            $normalized = $tree->normalize(['static' => 'env_test_FOO', 'dynamic' => 'env_test_FOO']);
        } finally {
            BaseNode::resetPlaceholders();
        }

        $this->assertSame(['static' => 'resolved', 'dynamic' => 'env_test_FOO'], $normalized);
    }

    public function testInlinedEnvVarsCanBeArrays()
    {
        $tree = (new TreeBuilder('root'))
            ->getRootNode()
                ->children()
                    ->arrayNode('locales')->inlineEnvVars()->scalarPrototype()->end()->end()
                ->end()
            ->end()
            ->buildTree()
        ;

        BaseNode::setPlaceholderUniquePrefix('env_test');
        BaseNode::setPlaceholderResolver(static fn (mixed $value): mixed => 'env_test_FOO' === $value ? ['en', 'fr'] : $value);

        try {
            $normalized = $tree->normalize(['locales' => 'env_test_FOO']);
        } finally {
            BaseNode::resetPlaceholders();
        }

        $this->assertSame(['locales' => ['en', 'fr']], $normalized);
    }

    public function testResetPlaceholdersDropsTheResolver()
    {
        $tree = (new TreeBuilder('root'))
            ->getRootNode()
                ->children()
                    ->scalarNode('static')->inlineEnvVars()->end()
                ->end()
            ->end()
            ->buildTree()
        ;

        BaseNode::setPlaceholderUniquePrefix('env_test');
        BaseNode::setPlaceholderResolver(static fn (mixed $value): mixed => 'resolved');
        BaseNode::resetPlaceholders();

        $this->assertSame(['static' => 'env_test_FOO'], $tree->normalize(['static' => 'env_test_FOO']));
    }
}
