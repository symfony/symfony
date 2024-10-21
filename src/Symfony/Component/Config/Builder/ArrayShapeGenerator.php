<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Builder;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\NumericNode;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;
use Symfony\Component\Config\Definition\StringNode;
use Symfony\Component\Config\Definition\VariableNode;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class ArrayShapeGenerator
{
    public static function generate(ArrayNode $node): string
    {
        return self::prependPhpDocWithStar(self::doGeneratePhpDoc($node));
    }

    private static function doGeneratePhpDoc(NodeInterface $node, int $nestingLevel = 1): string
    {
        if (!$node instanceof ArrayNode) {
            return $node->getName();
        }

        if ($node instanceof PrototypedArrayNode) {
            $isHashmap = (bool) $node->getKeyAttribute();

            $prototype = $node->getPrototype();
            if ($prototype instanceof ArrayNode) {
                return 'array<'.($isHashmap ? 'string, ' : '').self::doGeneratePhpDoc($prototype, $nestingLevel).'>';
            }

            return 'array<'.($isHashmap ? 'string, ' : '').self::handleScalarNode($prototype).'>';
        }

        if (!($children = $node->getChildren()) && !$node->getParent() instanceof PrototypedArrayNode) {
            return 'array<array-key, mixed>';
        }

        $arrayShape = \sprintf("array{%s\n", self::generateInlinePhpDocForNode($node));

        /** @var NodeInterface $child */
        foreach ($children as $child) {
            $arrayShape .= str_repeat(' ', $nestingLevel * 4).self::dumpNodeKey($child).': ';

            if ($child instanceof PrototypedArrayNode) {
                $isHashmap = (bool) $child->getKeyAttribute();

                $arrayShape .= 'array<'.($isHashmap ? 'string, ' : '').self::handleNode($child->getPrototype(), $nestingLevel).'>';
            } else {
                $arrayShape .= self::handleNode($child, $nestingLevel);
            }

            $arrayShape .= \sprintf(",%s\n", !$child instanceof ArrayNode ? self::generateInlinePhpDocForNode($child) : '');
        }

        return $arrayShape.str_repeat(' ', 4 * ($nestingLevel - 1)).'}';
    }

    private static function dumpNodeKey(NodeInterface $node): string
    {
        $name = $node->getName();
        $quoted = str_starts_with($name, '@')
            || \in_array(strtolower($name), ['int', 'float', 'bool', 'null', 'scalar'], true)
            || strpbrk($name, '\'"');

        if ($quoted) {
            $name = "'".addslashes($name)."'";
        }

        return $name.($node->isRequired() ? '' : '?');
    }

    private static function handleNumericNode(NumericNode $node): string
    {
        $min = $node->getMin() ?? 'min';
        $max = $node->getMax() ?? 'max';

        if ($node instanceof IntegerNode) {
            return \sprintf('int<%s, %s>', $min, $max);
        } elseif ($node instanceof FloatNode) {
            return 'float';
        }

        return \sprintf('int<%s, %s>|float', $min, $max);
    }

    private static function prependPhpDocWithStar(string $shape): string
    {
        return str_replace("\n", "\n * ", $shape);
    }

    private static function generateInlinePhpDocForNode(BaseNode $node): string
    {
        $comment = '';
        if ($node->hasDefaultValue() || $node->getInfo() || $node->isDeprecated()) {
            if ($node->isDeprecated()) {
                $comment .= 'Deprecated: '.$node->getDeprecation($node->getName(), $node->getPath())['message'].' ';
            }

            if ($info = $node->getInfo()) {
                $comment .= $info.' ';
            }

            if ($node->hasDefaultValue() && !\is_array($defaultValue = $node->getDefaultValue())) {
                $comment .= 'Default: '.json_encode($defaultValue, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
            }
        }

        return $comment ? ' // '.rtrim(preg_replace('/\s+/', ' ', $comment)) : '';
    }

    private static function handleNode(NodeInterface $node, int $nestingLevel): string
    {
        if ($node instanceof ArrayNode) {
            return self::doGeneratePhpDoc($node, 1 + $nestingLevel);
        }

        return self::handleScalarNode($node);
    }

    private static function handleScalarNode(NodeInterface $node): string
    {
        return match (true) {
            $node instanceof BooleanNode => 'bool',
            $node instanceof StringNode => 'string',
            $node instanceof NumericNode => self::handleNumericNode($node),
            $node instanceof EnumNode => $node->getPermissibleValues('|'),
            $node instanceof ScalarNode => 'string|int|float|bool',
            $node instanceof VariableNode => 'mixed',
        };
    }
}
