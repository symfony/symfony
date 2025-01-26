<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Builder\ExprBuilder;

/**
 * @experimental
 */
final readonly class JsonSchemaGenerator
{
    public function __construct(private string $outputPath)
    {
    }

    public function merge(NodeInterface $node, \stdClass $schema = new \stdClass()): void
    {
        if (file_exists($this->outputPath)) {
            $previousSchema = json_decode(file_get_contents($this->outputPath), flags: \JSON_THROW_ON_ERROR);
            foreach ($previousSchema as $key => $value) {
                $schema->$key ??= $value;
            }
        }

        if (!\in_array($ref = '#/definitions/'.$node->getName(), array_column($schema->anyOf ??= [], '$ref'))) {
            $schema->anyOf[] = (object) ['$ref' => $ref];
        }

        $this->build($node, $schema);
    }

    public function build(NodeInterface $node, \stdClass $schema = new \stdClass()): void
    {
        $rootNodeName = $node->getName() ?? 'root';

        $schema->{'$schema'} ??= 'http://json-schema.org/draft-06/schema#';
        if (!isset($schema->anyOf)) {
            $schema->{'$ref'} ??= '#/definitions/'.$rootNodeName;
        }
        $schema->definitions ??= new \stdClass();
        $schema->definitions->$rootNodeName = $this->buildSingleNode($node, $schema->definitions, allowParam: false);
        $schema->definitions->param = (object) [
            '$comment' => 'Container parameter',
            'type' => 'string',
            'pattern' => '^%[^%]+%$',
        ];

        file_put_contents($this->outputPath, json_encode($schema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
    }

    private function buildSingleNode(NodeInterface $node, \stdClass $definitions, bool $allowParam = true): \stdClass
    {
        $schema = (object) match (\count($types = $this->createSubSchemas($node, $definitions, $allowParam))) {
            1 => $types[0],
            default => ['anyOf' => $types],
        };

        if ($node->hasDefaultValue()) {
            $schema->default = $node->getDefaultValue();
        }

        if ($node instanceof BaseNode) {
            if ($info = $node->getInfo()) {
                $schema->description = $info;
            }

            if ($node->isDeprecated()) {
                $schema->deprecated = true;
                $schema->deprecationMessage = $node->getDeprecationMessage($node);
            }
        }

        if (!isset($schema->{'$ref'})) {
            $schema = $this->getReference($schema, $definitions);
        }

        return $schema;
    }

    private function createSubSchemas(NodeInterface $node, \stdClass $definitions, bool $allowParam = true): array
    {
        [$schema, $validateValue, $allowNull] = match (true) {
            $node instanceof BooleanNode => [(object) ['type' => 'boolean'], is_bool(...), null],
            $node instanceof IntegerNode => [$this->createNumericSchema($node), is_int(...), null],
            $node instanceof NumericNode => [$this->createNumericSchema($node), is_float(...), false],
            $node instanceof StringNode => [(object) ['type' => 'string'], is_string(...), ($node->isRequired() && $node->getAllowEmptyValue()) ?: null],
            $node instanceof EnumNode => [$schema = $this->createEnumSchema($node), static fn ($v) => \in_array($v, $schema->enum), null],
            $node instanceof PrototypedArrayNode => [$this->createArraySchema($node, $definitions), static fn () => false, null],
            $node instanceof ArrayNode => [$this->createObjectSchema($node, $definitions), static fn () => false, null],
            $node instanceof ScalarNode => [(object) ['type' => ['boolean', 'number', 'string']], is_scalar(...), null],
            default => [new \stdClass(), static fn () => true, null],
        };

        $allowNull ??= (!$node->isRequired() && ($node->hasDefaultValue() || ($node instanceof VariableNode && $node->getAllowEmptyValue())))
            || ($node->hasDefaultValue() && null === $node->getDefaultValue());

        $allowedExtraValues = [];
        $subSchemas = null;
        if ($node instanceof BaseNode && $normalizedTypes = $node->getNormalizedTypes()) {
            $allowedExtraValues = array_column($node->getEquivalentValues(), 0);
            $allowNull = $allowNull || \in_array(ExprBuilder::TYPE_NULL, $normalizedTypes);
            if (\in_array(ExprBuilder::TYPE_ANY, $normalizedTypes)) {
                // This will make IDEs not complain about configurations containing complex beforeNormalization logic
                $subSchemas[] = new \stdClass();
            }
            if (!\in_array('array', (array) ($schema->type ?? [])) && \in_array(ExprBuilder::TYPE_ARRAY, $normalizedTypes, true)) {
                $subSchemas[] = (object) ['type' => $allowNull ? ['array', 'null'] : 'array'];
            }
            if (!\in_array('string', (array) ($schema->type ?? [])) && \in_array(ExprBuilder::TYPE_STRING, $normalizedTypes, true)) {
                $subSchemas[] = (object) ['type' => $allowNull ? ['string', 'null'] : 'string'];
            }
        }

        if ($node->hasDefaultValue() && !\in_array($defaultValue = $node->getDefaultValue(), $allowedExtraValues, true)) {
            $allowedExtraValues[] = $defaultValue;
        }

        foreach ($allowedExtraValues as $i => $allowedExtraValue) {
            if (null !== $allowedExtraValue && !\is_scalar($allowedExtraValue)) {
                // IDEs don't seem to understand non-scalar values in "enum", so let's create separate sub-schema
                unset($allowedExtraValues[$i]);
                if (\is_array($allowedExtraValue) && array_is_list($allowedExtraValue)) {
                    $subSchemas[] = (object) ['const' => $allowedExtraValue];
                }
            }
        }

        if ($allowedValues = array_values(array_filter($allowedExtraValues, static fn (mixed $value) => !$validateValue($value)))) {
            if (!isset($schema->enum)) {
                // Append "boolean" to "type" instead of [true, false] to "enum"
                if (false !== ($true = array_search(true, $allowedValues, true)) && false !== ($false = array_search(false, $allowedValues, true))) {
                    unset($allowedValues[$true], $allowedValues[$false]);
                    $this->addTypeToSchema($schema, 'boolean');
                }

                // Append "null" to "type" instead of null to "enum"
                if (false !== ($null = array_search(null, $allowedValues, true))) {
                    unset($allowedValues[$null]);
                    $this->addTypeToSchema($schema, 'null');
                }

                if ($allowedValues) {
                    $subSchemas[] = (object) ['enum' => array_values($allowedValues)];
                }
            } else {
                $schema->enum = array_values(array_unique(array_merge($schema->enum, $allowedValues)));
            }
        }

        if ($schema) {
            $subSchemas[] = $schema;
            if ($allowParam && !\in_array('string', (array) ($schema->type ?? null))) {
                $subSchemas[] = (object) ['$ref' => '#/definitions/param'];
            }
        }

        $subSchemas ??= [new \stdClass()];

        return $subSchemas;
    }

    private function createArraySchema(PrototypedArrayNode $node, \stdClass $definitions): \stdClass
    {
        $prototypeSchema = $this->buildSingleNode($node->getPrototype(), $definitions);
        $prototypeRef = $this->getReference($prototypeSchema, $definitions);

        $schema = (object) [
            'type' => ['array', 'object'],
            'items' => $prototypeRef,
            'additionalProperties' => $prototypeRef,
        ];

        if ($node->getMinNumberOfElements() > 0) {
            $schema->minItems = $schema->minProperties = $node->getMinNumberOfElements();
        }

        return $schema;
    }

    private function createObjectSchema(ArrayNode $node, \stdClass $definitions): \stdClass
    {
        $schema = (object) [
            'type' => 'object',
        ];

        foreach ($node->getChildren() as $child) {
            $schema->properties ??= new \stdClass();
            $schema->properties->{$child->getName()} = $this->buildSingleNode($child, $definitions);
        }

        return $schema;
    }

    private function createEnumSchema(EnumNode $node): \stdClass
    {
        return (object) ['enum' => array_map(static fn ($v) => $v instanceof \UnitEnum ? \sprintf('!php/enum %s::%s', $v::class, $v->name) : $v, $node->getValues())];
    }

    private function createNumericSchema(NumericNode $node): \stdClass
    {
        $schema = (object) ['type' => $node instanceof IntegerNode ? 'integer' : 'number'];

        if (null !== ($min = $node->getMin())) {
            $schema->minimum = $min;
        }

        if (null !== ($max = $node->getMax())) {
            $schema->maximum = $max;
        }

        return $schema;
    }

    private function addTypeToSchema(\stdClass $schema, string $type): void
    {
        $schema->type = (array) ($schema->type ?? []);
        $schema->type[] = $type;
    }

    private function getReference(\stdClass $subSchema, \stdClass $definitions): \stdClass
    {
        $id = hash('xxh3', json_encode($subSchema));
        $definitions->$id ??= $subSchema;

        return (object) ['$ref' => '#/definitions/'.$id];
    }
}
