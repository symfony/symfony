<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Node;

use Symfony\Component\ExpressionLanguage\Compiler;
use Symfony\Component\ExpressionLanguage\Exception\RuntimeException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class GetAttrNode extends Node
{
    public const PROPERTY_CALL = 1;
    public const METHOD_CALL = 2;
    public const ARRAY_CALL = 3;

    /**
     * @param self::* $type
     * @param bool    $isNullSafe Whether the access uses the `?.[]` null-safe operator; only honored for self::ARRAY_CALL.
     *                            For self::PROPERTY_CALL and self::METHOD_CALL, null-safety is driven by ConstantNode::$isNullSafe.
     */
    public function __construct(Node $node, Node $attribute, ArrayNode $arguments, int $type, bool $isNullSafe = false)
    {
        $isNullSafe = self::ARRAY_CALL === $type && $isNullSafe;

        parent::__construct(
            ['node' => $node, 'attribute' => $attribute, 'arguments' => $arguments],
            ['type' => $type, 'is_null_coalesce' => false, 'is_null_safe' => $isNullSafe],
        );
    }

    public function compile(Compiler $compiler): void
    {
        $nullSafe = ($this->nodes['attribute'] instanceof ConstantNode && $this->nodes['attribute']->isNullSafe) || $this->attributes['is_null_safe'];
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                $compiler
                    ->compile($this->nodes['node'])
                    ->raw($nullSafe ? '?->' : '->')
                    ->raw($this->nodes['attribute']->attributes['value'])
                ;
                break;

            case self::METHOD_CALL:
                $compiler
                    ->compile($this->nodes['node'])
                    ->raw($nullSafe ? '?->' : '->')
                    ->raw($this->nodes['attribute']->attributes['value'])
                    ->raw('(')
                    ->compile($this->nodes['arguments'])
                    ->raw(')')
                ;
                break;

            case self::ARRAY_CALL:
                if ($nullSafe) {
                    $compiler
                        ->raw('\\'.self::class.'::convertToArrayAccess(')
                        ->compile($this->nodes['node'])
                        ->raw(', ')
                        ->string($this->nodes['node']->dump())
                        ->raw(')?->offsetGet(')
                        ->compile($this->nodes['attribute'])
                        ->raw(')')
                    ;
                } else {
                    $compiler
                        ->compile($this->nodes['node'])
                        ->raw('[')
                        ->compile($this->nodes['attribute'])->raw(']')
                    ;
                }
                break;
        }
    }

    public function evaluate(array $functions, array $values): mixed
    {
        $isShortCircuited = false;

        return $this->evaluateChain($functions, $values, $isShortCircuited);
    }

    /**
     * @internal
     */
    public static function convertToArrayAccess(mixed $value, string $nodeDump): ?\ArrayAccess
    {
        if (null === $value) {
            return null;
        }

        if (\is_array($value)) {
            return new \ArrayObject($value);
        }

        if ($value instanceof \ArrayAccess) {
            return $value;
        }

        throw new RuntimeException(\sprintf('Unable to get an item of non-array "%s".', $nodeDump));
    }

    public function toArray(): array
    {
        $nullSafe = $this->nodes['attribute'] instanceof ConstantNode && $this->nodes['attribute']->isNullSafe;
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                return [$this->nodes['node'], $nullSafe ? '?.' : '.', $this->nodes['attribute']];

            case self::METHOD_CALL:
                return [$this->nodes['node'], $nullSafe ? '?.' : '.', $this->nodes['attribute'], '(', $this->nodes['arguments'], ')'];

            case self::ARRAY_CALL:
                return [$this->nodes['node'], $this->attributes['is_null_safe'] ? '?.[' : '[', $this->nodes['attribute'], ']'];
        }
    }

    /**
     * Provides BC with instances serialized before v6.2.
     */
    public function __unserialize(array $data): void
    {
        $this->nodes = $data['nodes'];
        $this->attributes = $data['attributes'];
        $this->attributes['is_null_coalesce'] ??= false;
        $this->attributes['is_null_safe'] ??= false;
        unset($this->attributes['is_short_circuited']);
    }

    /**
     * Evaluates the chain of accesses this node ends, sharing whether a null-safe operator short-circuited it.
     */
    private function evaluateChain(array $functions, array $values, bool &$isShortCircuited): mixed
    {
        $nullSafe = $this->attributes['is_null_safe'];
        $node = $this->nodes['node'];
        $value = $node instanceof self ? $node->evaluateChain($functions, $values, $isShortCircuited) : $node->evaluate($functions, $values);

        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                if (null === $value && ($this->nodes['attribute']->isNullSafe || $this->attributes['is_null_coalesce'])) {
                    $isShortCircuited = true;

                    return null;
                }
                if (null === $value && $isShortCircuited) {
                    return null;
                }

                if (!\is_object($value) && !$this->attributes['is_null_coalesce']) {
                    throw new RuntimeException(\sprintf('Unable to get property "%s" of non-object "%s".', $this->nodes['attribute']->dump(), $this->nodes['node']->dump()));
                }

                $property = $this->nodes['attribute']->attributes['value'];

                if ($this->attributes['is_null_coalesce']) {
                    return $value->$property ?? null;
                }

                return $value->$property;

            case self::METHOD_CALL:
                if (null === $value && $this->nodes['attribute']->isNullSafe) {
                    $isShortCircuited = true;

                    return null;
                }
                if (null === $value && $isShortCircuited) {
                    return null;
                }

                if (!\is_object($value)) {
                    throw new RuntimeException(\sprintf('Unable to call method "%s" of non-object "%s".', $this->nodes['attribute']->dump(), $this->nodes['node']->dump()));
                }
                if (!\is_callable($toCall = [$value, $this->nodes['attribute']->attributes['value']])) {
                    throw new RuntimeException(\sprintf('Unable to call method "%s" of object "%s".', $this->nodes['attribute']->attributes['value'], get_debug_type($value)));
                }

                return $toCall(...array_values($this->nodes['arguments']->evaluate($functions, $values)));

            case self::ARRAY_CALL:
                if (null === $value && ($nullSafe || $isShortCircuited)) {
                    $isShortCircuited = true;

                    return null;
                }

                if (!\is_array($value) && !$value instanceof \ArrayAccess && (!$this->attributes['is_null_coalesce'] || \is_object($value))) {
                    throw new RuntimeException(\sprintf('Unable to get an item of non-array "%s".', $this->nodes['node']->dump()));
                }

                if ($this->attributes['is_null_coalesce']) {
                    return $value[$this->nodes['attribute']->evaluate($functions, $values)] ?? null;
                }

                return $value[$this->nodes['attribute']->evaluate($functions, $values)];
        }
    }
}
