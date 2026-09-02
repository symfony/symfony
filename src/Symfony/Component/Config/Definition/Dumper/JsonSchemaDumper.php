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
        'object' => ['type' => 'object'],
        'object_null' => ['type' => ['object', 'null']],
    ];

    private array $typeDefs;

    /**
     * @param array<array<string, mixed>> $parameterSchemas additional JSON Schema fragments included as anyOf options for scalar nodes
     */
    public function __construct(
        private readonly array $parameterSchemas = [],
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
        if ($node instanceof PrototypedArrayNode) {
            $prototypeSchema = $this->dumpNode($node->getPrototype());

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
            }
        } elseif ($node instanceof ArrayNode) {
            $schema = ['$ref' => $node->isNullable() ? '#/$defs/types/object_null' : '#/$defs/types/object'];
            $children = $node->getChildren();
            foreach ($children as $child) {
                $schema['properties'] ??= [];
                $schema['properties'][$child->getName()] = $this->dumpNode($child);
                if ($child->isRequired()) {
                    $schema['required'] ??= [];
                    $schema['required'][] = $child->getName();
                }
            }

            if (isset($schema['properties'])) {
                $schema['additionalProperties'] = $node->shouldIgnoreExtraKeys();
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

            if ($normalizedTypes) {
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

    /** @return array<string, mixed> */
    private function buildTypeDefs(): array
    {
        if (!$this->parameterSchemas) {
            return self::PARAMETERIZABLE_DEFS;
        }

        return array_map(fn ($def) => ['anyOf' => [$def, ...$this->parameterSchemas]], self::PARAMETERIZABLE_DEFS);
    }
}
