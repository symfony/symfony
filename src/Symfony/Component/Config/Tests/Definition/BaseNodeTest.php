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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\NodeInterface;

class BaseNodeTest extends TestCase
{
    /**
     * @dataProvider providePath
     */
    public function testGetPathForChildNode(string $expected, array $params)
    {
        $constructorArgs = [];
        $constructorArgs[] = $params[0];

        if (isset($params[1])) {
            $parent = $this->createMock(NodeInterface::class);
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

            protected function normalizeValue($value)
            {
                return null;
            }

            protected function mergeValues($leftSide, $rightSide)
            {
                return null;
            }

            protected function finalizeValue($value)
            {
                return null;
            }

            public function hasDefaultValue(): bool
            {
                return true;
            }

            public function getDefaultValue()
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
}
