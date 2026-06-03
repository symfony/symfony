<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Type;

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Represents the exact shape of an anonymous object.
 *
 * The shape is stored sorted by key; two instances built from the same entries in
 * a different order compare equal through getShape() and produce the same string
 * representation. Object shapes are always sealed: unlike ArrayShapeType, no
 * extra-key/extra-value type is supported because the underlying
 * phpstan-phpdoc-parser AST node has no such notion.
 *
 * Presence and value checks in accepts() both look at the public, initialized
 * properties returned by get_object_vars(); non-public properties, uninitialized
 * typed properties and state exposed only through __get() or other magic
 * accessors are not visible.
 *
 * @author Benjamin Franzke <ben@bnf.dev>
 */
final class ObjectShapeType extends Type
{
    /**
     * @var array<string, array{type: Type, optional?: bool}>
     */
    private readonly array $shape;

    /**
     * @param array<string, array{type: Type, optional?: bool}> $shape
     */
    public function __construct(
        array $shape,
    ) {
        $sortedShape = $shape;
        ksort($sortedShape);

        $this->shape = $sortedShape;
    }

    public function getTypeIdentifier(): TypeIdentifier
    {
        return TypeIdentifier::OBJECT;
    }

    /**
     * @return array<string, array{type: Type, optional?: bool}>
     */
    public function getShape(): array
    {
        return $this->shape;
    }

    public function isIdentifiedBy(TypeIdentifier|string ...$identifiers): bool
    {
        foreach ($identifiers as $identifier) {
            if (TypeIdentifier::OBJECT === $identifier || TypeIdentifier::OBJECT->value === $identifier) {
                return true;
            }
        }

        return false;
    }

    public function accepts(mixed $value): bool
    {
        if (!\is_object($value)) {
            return false;
        }

        $vars = get_object_vars($value);

        foreach ($this->shape as $key => $shapeValue) {
            if (!($shapeValue['optional'] ?? false) && !\array_key_exists($key, $vars)) {
                return false;
            }
        }

        foreach ($vars as $key => $itemValue) {
            $valueType = $this->shape[$key]['type'] ?? false;
            if (!$valueType) {
                return false;
            }

            if (!$valueType->accepts($itemValue)) {
                return false;
            }
        }

        return true;
    }

    public function __toString(): string
    {
        $items = [];

        foreach ($this->shape as $key => $value) {
            $itemKey = \sprintf("'%s'", $key);
            if ($value['optional'] ?? false) {
                $itemKey = \sprintf('%s?', $itemKey);
            }

            $items[] = \sprintf('%s: %s', $itemKey, $value['type']);
        }

        return \sprintf('object{%s}', implode(', ', $items));
    }
}
