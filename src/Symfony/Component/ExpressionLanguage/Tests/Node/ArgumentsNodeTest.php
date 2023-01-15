<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests\Node;

use Symfony\Component\ExpressionLanguage\Node\ArgumentsNode;

class ArgumentsNodeTest extends ArrayNodeTest
{
    public static function getCompileData(): array
    {
        return [
            ['"a", "b"', static::getArrayNode()],
        ];
    }

    public static function getDumpData(): \Generator
    {
        yield from [
            ['"a", "b"', static::getArrayNode()],
        ];
    }

    protected static function createArrayNode(): \Symfony\Component\ExpressionLanguage\Node\ArrayNode
    {
        return new ArgumentsNode();
    }
}
