<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Dumper;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\Builder\ExprBuilder;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\NumericNode;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;
use Symfony\Component\Config\Definition\StringNode;
use Symfony\Component\Config\Definition\VariableNode;

/**
 * Dumps a JSON Schema from a Node definition.
 *
 * @author Jérôme Tamarelle<jerome@tamarelle.net>
 */
final class JsonSchemaDumper
{
    private const PARAMETERIZABLE_DEFS = [
        'string' => ['type' => 'string'],
        'string_null' => ['type' => ['string', 'null']],
        'boolean' => ['type' => 'boolean'],
        'boolean_null' => ['type' => ['boolean', 'null']],
        'integer' => ['type' => 'integer'],
        'integer_null' => ['type' => ['integer', 'null']],
        'number' => ['type' => 'number'],
        'number_null' => ['type' => ['number', 'null']],
    ];

    private const STRUCTURAL_DEFS = [
        'variable' => ['type' => ['array', 'boolean', 'null', 'number', 'object', 'string']],
        'scalar' => ['type' => ['boolean', 'null', 'number', 'string']],
        'array' => ['type' => 'array'],
        'array_null' => ['type' => ['array', 'null']],
        // PHP decodes an empty map and an empty list to the same array, so a map accepts an empty list
        'object' => ['type' => ['object', 'array'], 'maxItems' => 0],
        'object_null' => ['type' => ['object', 'array', 'null'], 'maxItems' => 0],
    ];

    private array $typeDefs;
    private bool $inListItem = false;

    /**
     * @param array<array<string, mixed>>             $parameterSchemas additional JSON Schema fragments included as anyOf options for scalar nodes
     * @param (\Closure(string $alias): ?string)|null $resolveAlias     returns the "$ref" pointer to the schema of the configuration rooted at the given name, or null when unknown
     */
    public function __construct(
        private readonly array $parameterSchemas = [],
        private readonly ?\Closure $resolveAlias = null,
    ) {
        $this->typeDefs = $this->buildTypeDefs();
    }

    /**
     * Returns all shared definitions nested under "types".
     * In a full schema, include this under "$defs" so that "$ref": "#/$defs/types/..." resolves correctly.
     *
     * @return array{types: array<string, mixed>}
     */
    public function getAllDefs(): array
    {
        return ['types' => array_merge(self::STRUCTURAL_DEFS, $this->typeDefs)];
    }

    /**
     * Dump a full JSON Schema from the given root node.
     *
     * @param array<string, mixed> $meta Additional attributes to include in the schema root
     */
    public function dump(NodeInterface $node, array $meta = []): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            ...$meta,
            '$defs' => $this->getAllDefs(),
            ...$this->dumpNode($node),
        ];
    }

    public function dumpNode(NodeInterface $node): array
    {
        $elementsAtParentLevel = $node instanceof ArrayNode ? $this->getChildrenWithElementsAtParentLevel($node) : [];
        $acceptsAnyValue = !$elementsAtParentLevel && $node instanceof BaseNode && \in_array(ExprBuilder::TYPE_ANY, $node->getNormalizedTypes(), true);

        if ($node instanceof BaseNode && null !== ($alias = $node->getAttribute('alias_of')) && null !== $ref = $this->resolveAlias?->__invoke($alias)) {
            $schema = ['$ref' => $ref];
        } elseif ($acceptsAnyValue) {
            // the closure can turn any value into a valid one
            $schema = ['$ref' => '#/$defs/types/variable'];
        } elseif ($node instanceof PrototypedArrayNode) {
            $prototypeSchema = $this->dumpPrototype($node);

            if ($node->getKeyAttribute()) {
                $schema = [
                    '$ref' => $node->isNullable() ? '#/$defs/types/object_null' : '#/$defs/types/object',
                    'additionalProperties' => $prototypeSchema,
                ];

                if ($node->getMinNumberOfElements()) {
                    $schema['minProperties'] = $node->getMinNumberOfElements();
                }

                // A normalization closure rewrites the input before the key-attribute
                // rules apply, so such a map also accepts the equivalent list form.
                if ($node->hasNormalizationClosures() && !$node->getPrototype() instanceof ArrayNode) {
                    $listSchema = [
                        '$ref' => $node->isNullable() ? '#/$defs/types/array_null' : '#/$defs/types/array',
                        'items' => $prototypeSchema,
                    ];

                    if ($node->getMinNumberOfElements()) {
                        $listSchema['minItems'] = $node->getMinNumberOfElements();
                    }

                    $schema = ['anyOf' => [$schema, $listSchema]];
                }
            } else {
                $schema = [
                    '$ref' => $node->isNullable() ? '#/$defs/types/array_null' : '#/$defs/types/array',
                    'items' => $prototypeSchema,
                ];

                if ($node->getMinNumberOfElements()) {
                    $schema['minItems'] = $node->getMinNumberOfElements();
                }

                if (null !== $node->getAttribute('wraps_arrays_into')) {
                    $schema = ['anyOf' => [$schema, $this->dumpWrappedMap($node)]];
                }
            }
        } elseif ($node instanceof ArrayNode) {
            $schema = ['$ref' => $node->isNullable() ? '#/$defs/types/object_null' : '#/$defs/types/object'];
            $schema = [...$schema, ...$this->dumpArrayNodeProperties($node)];

            if (null !== $key = $node->getAttribute('wraps_arrays_into')) {
                $schema = ['anyOf' => [$schema, ...$this->dumpWrappedArrays($node, $key, $schema)]];
            }

            foreach ($elementsAtParentLevel as $child) {
                $schema = ['anyOf' => [$schema, ...$this->dumpElementsAtParentLevel($node, $child, $schema)]];
            }
        } elseif ($node instanceof BooleanNode) {
            $schema = ['$ref' => $node->isNullable() ? '#/$defs/types/boolean_null' : '#/$defs/types/boolean'];
        } elseif ($node instanceof StringNode) {
            $schema = ['$ref' => $node->isNullable() ? '#/$defs/types/string_null' : '#/$defs/types/string'];
        } elseif ($node instanceof EnumNode) {
            $enumValues = [];
            foreach ($node->getValues() as $case) {
                if ($case instanceof \BackedEnum) {
                    $enumValues[] = \sprintf('!php/enum %s::%s', $case::class, $case->name);
                    $enumValues[] = $case->value;
                } elseif ($case instanceof \UnitEnum) {
                    $enumValues[] = \sprintf('!php/enum %s::%s', $case::class, $case->name);
                } else {
                    $enumValues[] = $case;
                }
            }
            if (null !== $node->getEnumFqcn() && !\in_array(null, $enumValues, true)) {
                // a node bound to an enum FQCN accepts null and maps it to null
                $enumValues[] = null;
            }
            $schema = ['enum' => $enumValues];
            if ($this->parameterSchemas) {
                $schema = ['anyOf' => [$schema, ...$this->parameterSchemas]];
            }
        } elseif ($node instanceof NumericNode) {
            $type = $node instanceof IntegerNode ? 'integer' : 'number';
            $schema = ['$ref' => '#/$defs/types/'.($node->isNullable() ? $type.'_null' : $type)];

            if (null !== $node->getMin()) {
                $schema['minimum'] = $node->getMin();
            }

            if (null !== $node->getMax()) {
                $schema['maximum'] = $node->getMax();
            }
        } elseif ($node instanceof ScalarNode) {
            $schema = ['$ref' => '#/$defs/types/scalar'];
        } elseif ($node instanceof VariableNode) {
            $schema = ['$ref' => '#/$defs/types/variable'];
        } else {
            $schema = ['type' => ['null']];
        }

        if ($node instanceof BaseNode) {
            $normalizedTypes = array_diff($node->getNormalizedTypes(), [ExprBuilder::TYPE_ANY, ExprBuilder::TYPE_ARRAY]);

            if (isset($schema['type']) && $node->hasDefaultValue() && null === $node->getDefaultValue()) {
                $normalizedTypes[] = 'null';
            }

            if ($normalizedTypes && !$acceptsAnyValue) {
                $normalizedTypesSchema = [
                    'type' => array_values(
                        array_unique(
                            array_merge(
                                ...array_map(static fn ($type) => match ($type) {
                                    ExprBuilder::TYPE_INT => ['integer'],
                                    ExprBuilder::TYPE_STRING => ['string'],
                                    ExprBuilder::TYPE_BOOL => ['boolean'],
                                    ExprBuilder::TYPE_ARRAY => ['array', 'object'],
                                    ExprBuilder::TYPE_NULL => ['null'],
                                    ExprBuilder::TYPE_BACKED_ENUM => ['string'],
                                    ExprBuilder::TYPE_ANY => throw new \UnexpectedValueException('TYPE_ANY is not expected as normalized type.'),
                                }, $normalizedTypes),
                            ),
                        ),
                    ),
                ];
                if (isset($schema['anyOf'])) {
                    $schema['anyOf'][] = $normalizedTypesSchema;
                } else {
                    $schema = ['anyOf' => [$schema, $normalizedTypesSchema]];
                }
            }

            if ($node->hasDefaultValue() && !($node instanceof ArrayNode && [] === $node->getDefaultValue())) {
                $schema['default'] = $node->getDefaultValue();
            }

            if ($node->getInfo()) {
                $schema['description'] = $node->getInfo();
            }

            if ($node->getExample()) {
                $schema['examples'] = [$node->getExample()];
            }

            if ($node->isDeprecated()) {
                $schema['deprecated'] = true;
                $deprecationMessage = $node->getDeprecationMessage($node);
                if (str_starts_with($deprecationMessage, 'Since ')) {
                    $deprecationMessage = 'Deprecated since '.substr($deprecationMessage, 6);
                }
                $schema['description'] = isset($schema['description'])
                    ? $deprecationMessage."\n\n".$schema['description']
                    : $deprecationMessage;
            }
        }

        if (\is_array($schema['type'] ?? null) && 1 === \count($schema['type'])) {
            $schema['type'] = $schema['type'][0];
        }

        return $schema;
    }

    private function dumpPrototype(PrototypedArrayNode $node): array
    {
        $inListItem = $this->inListItem;
        $this->inListItem = $inListItem || !$node->getKeyAttribute();

        try {
            return $this->dumpNode($node->getPrototype());
        } finally {
            $this->inListItem = $inListItem;
        }
    }

    /**
     * Describes the keys declared by the children of an array node.
     *
     * @return array<string, mixed>
     */
    private function dumpArrayNodeProperties(ArrayNode $node): array
    {
        $schema = [];
        $children = $node->getChildren();

        foreach ($children as $child) {
            $schema['properties'] ??= [];
            $schema['properties'][$child->getName()] = $this->dumpNode($child);
            // another file can provide the child, except in the items of a list, which are never merged
            if ($this->inListItem && $child->isRequired()) {
                $schema['required'] ??= [];
                $schema['required'][] = $child->getName();
            }
        }

        foreach ($node->getXmlRemappings() as [$singular, $plural]) {
            if (!isset($children[$plural]) || isset($children[$singular])) {
                continue;
            }

            // a pattern accepts the singular key without proposing it to completion
            $schema['patternProperties']['^'.preg_quote($singular).'$'] = $children[$plural] instanceof PrototypedArrayNode && !$children[$plural]->getKeyAttribute()
                ? ['anyOf' => [$this->dumpPrototype($children[$plural]), $schema['properties'][$plural]]]
                : ['$ref' => '#/$defs/types/variable'];
        }

        if (isset($schema['properties'])) {
            $schema['additionalProperties'] = $node->shouldIgnoreExtraKeys();
        }

        return $schema;
    }

    /**
     * Returns the children whose elements can be given in this node instead of in the child.
     *
     * @return list<PrototypedArrayNode>
     */
    private function getChildrenWithElementsAtParentLevel(ArrayNode $node): array
    {
        return array_values(array_filter(
            $node->getChildren(),
            static fn (NodeInterface $child) => $child instanceof PrototypedArrayNode && null !== $child->getAttribute('elements_at_parent_level'),
        ));
    }

    /**
     * Describes the elements of the given child as the parent node accepts them.
     *
     * The keys of the elements are given next to the keys of the parent node, which a normalization closure rewrites into the elements of the child.
     *
     * @param array<string, mixed> $schema the schema of the parent node
     *
     * @return list<array<string, mixed>>
     */
    private function dumpElementsAtParentLevel(ArrayNode $node, PrototypedArrayNode $child, array $schema): array
    {
        $element = $child->getPrototype();
        // the key that names the elements is consumed by the child, so the elements cannot provide it
        $keyAttribute = $child->getKeyAttribute();
        $consumedKeys = null === $keyAttribute ? [] : [$keyAttribute];
        $prototypeSchema = $this->dumpPrototype($child);

        $schemas = [];
        foreach ($prototypeSchema['anyOf'] ?? [$prototypeSchema] as $elementSchema) {
            if (!isset($elementSchema['properties'])) {
                if ('#/$defs/types/variable' !== ($elementSchema['$ref'] ?? null) || !$element instanceof ArrayNode) {
                    // the elements have no key that can be given in the parent node
                    continue;
                }

                // the elements accept any value, but the node still declares the keys of the shape
                $elementSchema = $this->dumpArrayNodeProperties($element);
            }

            $flatSchema = $schema;
            unset($flatSchema['properties'][$child->getName()]);

            foreach ($node->getXmlRemappings() as [$singular, $plural]) {
                if ($plural === $child->getName()) {
                    unset($flatSchema['patternProperties']['^'.preg_quote($singular).'$']);
                }
            }

            foreach (['properties', 'patternProperties'] as $key) {
                $elementProperties = $elementSchema[$key] ?? [];

                if ('properties' === $key) {
                    // the keys that name the elements are consumed by the child
                    $elementProperties = array_diff_key($elementProperties, array_flip($consumedKeys));
                }

                foreach ($elementProperties as $name => $propertySchema) {
                    $flatSchema[$key][$name] = $propertySchema;
                }
            }

            if (isset($flatSchema['properties']) && !$flatSchema['properties']) {
                unset($flatSchema['properties']);
            }

            if ($required = array_diff($elementSchema['required'] ?? [], $consumedKeys)) {
                $flatSchema['required'] = [...$flatSchema['required'] ?? [], ...$required];
            }

            $flatSchema['additionalProperties'] = $elementSchema['additionalProperties'] ?? false;

            $schemas[] = $flatSchema;
        }

        return $schemas;
    }

    /**
     * Describes the arrays that normalization wraps into the given child, as they do not set it.
     *
     * @return list<array<string, mixed>>
     */
    private function dumpWrappedArrays(ArrayNode $node, string $key, array $schema): array
    {
        $excludedKeys = [$key];
        foreach ($node->getXmlRemappings() as [$singular, $plural]) {
            if ($key === $plural) {
                $excludedKeys[] = $singular;
                unset($schema['patternProperties']['^'.preg_quote($singular).'$']);
            }
        }

        $wrapped = $schema['properties'][$key];
        unset($schema['properties'][$key]);

        $schemas = [];
        foreach ($wrapped['anyOf'] ?? [$wrapped] as $wrappedSchema) {
            $ref = $wrappedSchema['$ref'] ?? '';

            if (str_starts_with($ref, '#/$defs/types/array')) {
                // a list goes into the child as a whole
                $schemas[] = $wrappedSchema;
            } elseif (str_starts_with($ref, '#/$defs/types/object')) {
                unset($wrappedSchema['default'], $wrappedSchema['description'], $wrappedSchema['examples'], $wrappedSchema['deprecated']);

                // the keys that name the other children are not wrapped
                if ($properties = $schema['properties'] + ($wrappedSchema['properties'] ?? [])) {
                    $wrappedSchema['properties'] = $properties;
                }

                if ($patternProperties = ($schema['patternProperties'] ?? []) + ($wrappedSchema['patternProperties'] ?? [])) {
                    $wrappedSchema['patternProperties'] = $patternProperties;
                }

                $wrappedSchema['propertyNames'] = ['not' => ['enum' => $excludedKeys]];
                $schemas[] = $wrappedSchema;
            }
        }

        return $schemas;
    }

    /**
     * Describes the map whose keys normalization puts into the items of the list.
     *
     * @return array<string, mixed>
     */
    private function dumpWrappedMap(PrototypedArrayNode $node): array
    {
        $inListItem = $this->inListItem;
        // the items of a map are merged by key, so another file can provide their children
        $this->inListItem = false;

        try {
            $schema = [
                '$ref' => $node->isNullable() ? '#/$defs/types/object_null' : '#/$defs/types/object',
                'additionalProperties' => $this->dumpNode($node->getPrototype()),
            ];
        } finally {
            $this->inListItem = $inListItem;
        }

        if ($node->getMinNumberOfElements()) {
            $schema['minProperties'] = $node->getMinNumberOfElements();
        }

        return $schema;
    }

    /** @return array<string, mixed> */
    private function buildTypeDefs(): array
    {
        if (!$this->parameterSchemas) {
            return self::PARAMETERIZABLE_DEFS;
        }

        return array_map(fn ($def) => ['anyOf' => [$def, ...$this->parameterSchemas]], self::PARAMETERIZABLE_DEFS);
    }
}
