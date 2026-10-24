<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Read;

use Psr\Container\ContainerInterface;
use Symfony\Component\JsonStreamer\DataModel\Read\BackedEnumNode;
use Symfony\Component\JsonStreamer\DataModel\Read\CollectionNode;
use Symfony\Component\JsonStreamer\DataModel\Read\CompositeNode;
use Symfony\Component\JsonStreamer\DataModel\Read\DataModelNodeInterface;
use Symfony\Component\JsonStreamer\DataModel\Read\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Read\ScalarNode;
use Symfony\Component\JsonStreamer\Exception\InvalidStreamException;
use Symfony\Component\JsonStreamer\Exception\LogicException;
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;
use Symfony\Component\JsonStreamer\PhpGeneratorTrait;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Generates PHP code that reads JSON stream.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class PhpGenerator
{
    use PhpGeneratorTrait;

    public function __construct(
        private ContainerInterface $transformers,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    public function generate(DataModelNodeInterface $dataModel, bool $decodeFromStream, array $options = [], array $context = []): string
    {
        $context['indentation_level'] = 1;
        $context['object_nodes'] = $decodeFromStream ? $this->collectObjectNodes($dataModel) : [];

        $providers = $this->generateProviders($dataModel, $decodeFromStream, $context);

        $context['indentation_level'] = 0;
        $docType = str_replace('*/', '*\/', (string) $dataModel->getType());

        if ($decodeFromStream) {
            return $this->line('<?php', $context)
                .$this->line('', $context)
                .$this->line('/**', $context)
                .$this->line(' * @return '.$docType, $context)
                .$this->line(' */', $context)
                .$this->line('return static function (mixed $stream, \\'.ContainerInterface::class.' $transformers, \\'.LazyInstantiator::class.' $instantiator, array $options): mixed {', $context)
                .$providers
                .($this->canBeDecodedWithJsonDecode($dataModel, false)
                    ? $this->line('    return \\'.Decoder::class.'::decodeStream($stream, 0, null);', $context)
                    : $this->line('    return $providers['.var_export($dataModel->getIdentifier(), true).']($stream, 0, null);', $context))
                .$this->line('};', $context);
        }

        return $this->line('<?php', $context)
            .$this->line('', $context)
            .$this->line('/**', $context)
            .$this->line(' * @return '.$docType, $context)
            .$this->line(' */', $context)
            .$this->line('return static function (string|\\Stringable $string, \\'.ContainerInterface::class.' $transformers, array $options): mixed {', $context)
            .$providers
            .($this->canBeDecodedWithJsonDecode($dataModel, true)
                ? $this->line('    return \\'.Decoder::class.'::decodeString((string) $string);', $context)
                : $this->line('    return $providers['.var_export($dataModel->getIdentifier(), true).'](\\'.Decoder::class.'::decodeString((string) $string));', $context))
            .$this->line('};', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function generateProviders(DataModelNodeInterface $node, bool $decodeFromStream, array &$context): string
    {
        if ($node instanceof ObjectNode && $node->isMock()) {
            if (!isset($context['object_nodes'][$node->getIdentifier()])) {
                return '';
            }

            $node = $context['object_nodes'][$node->getIdentifier()];
        }

        $providerAccessor = $this->providerAccessor($node, $context);

        if ($context['providers'][$providerAccessor] ?? false) {
            return '';
        }

        $context['providers'][$providerAccessor] = true;

        $denormalize = $context['denormalize'] ?? false;
        $keepDecodedCollections = !$decodeFromStream && !$denormalize;
        $uses = match (true) {
            $decodeFromStream => '$options, $transformers, $instantiator, &$providers',
            $denormalize => '$options, $transformers, &$denormalizers',
            default => '$options, $transformers, &$providers',
        };

        if ($this->canBeDecodedWithJsonDecode($node, $keepDecodedCollections)) {
            return '';
        }

        if ($node instanceof ScalarNode || $node instanceof BackedEnumNode) {
            $accessor = $decodeFromStream ? '\\'.Decoder::class.'::decodeStream($stream, $offset, $length)' : '$data';
            $arguments = $decodeFromStream ? '$stream, $offset, $length' : '$data';

            return $this->line("$providerAccessor = static function ($arguments) {", $context)
                .$this->line('    return '.$this->generateValueFormat($node, $accessor).';', $context)
                .$this->line('};', $context);
        }

        if ($node instanceof CompositeNode) {
            $php = '';
            foreach ($node->getNodes() as $n) {
                if (!$this->canBeDecodedWithJsonDecode($n, $keepDecodedCollections)) {
                    $php .= $this->generateProviders($n, $decodeFromStream, $context);
                }
            }

            $arguments = $decodeFromStream ? '$stream, $offset, $length' : '$data';

            $php .= $this->line("$providerAccessor = static function ($arguments) use ($uses) {", $context);

            ++$context['indentation_level'];

            $php .= $decodeFromStream ? $this->line('$data = \\'.Decoder::class.'::decodeStream($stream, $offset, $length);', $context) : '';

            foreach ($node->getNodes() as $n) {
                $value = $this->canBeDecodedWithJsonDecode($n, $keepDecodedCollections) ? $this->generateValueFormat($n, '$data') : $this->providerAccessor($n, $context)."($arguments)";
                $php .= $this->line('if ('.$this->generateCompositeNodeItemCondition($n, '$data', $denormalize).') {', $context)
                    .$this->line("    return $value;", $context)
                    .$this->line('}', $context);
            }

            $php .= $this->line($this->generateUnexpectedValueThrow($node, '$data').';', $context);

            --$context['indentation_level'];

            return $php.$this->line('};', $context);
        }

        if ($node instanceof CollectionNode) {
            $itemNode = $node->getItemNode();
            $itemContext = $context;
            $itemDecodeFromStream = $decodeFromStream;

            if ($decodeFromStream && $this->isStructured($itemNode) && $this->canBeReadAtOnce($itemNode, $context['object_nodes'])) {
                $itemContext['denormalize'] = true;
                $itemDecodeFromStream = false;
                $uses .= ', &$readers';
            }

            $arguments = $decodeFromStream ? '$stream, $offset, $length' : '$data';

            $php = $this->line("$providerAccessor = static function ($arguments) use ($uses) {", $context);

            ++$context['indentation_level'];

            $collectionKeyType = $node->getType()->getCollectionKeyType();
            $hasIntKeys = $this->hasIntKeys($node->getType());
            $splitMethod = match (true) {
                $hasIntKeys => 'splitList',
                $collectionKeyType instanceof BuiltinType && TypeIdentifier::STRING === $collectionKeyType->getTypeIdentifier() => 'splitDict',
                default => 'splitCollection',
            };

            if ($decodeFromStream) {
                $php .= $this->line('$data = \\'.Splitter::class.'::'.$splitMethod.'($stream, $offset, $length);', $context);
            } elseif ($denormalize) {
                $php .= $this->line('if (!('.$this->generateCollectionCondition($node->getType(), '$data').')) {', $context)
                    .$this->line('    '.$this->generateUnexpectedValueThrow($node, '$data').';', $context)
                    .$this->line('}', $context);
            }

            if ($this->canBeDecodedWithJsonDecode($itemNode, $keepDecodedCollections)) {
                $itemPhp = match (true) {
                    $decodeFromStream => $this->generateValueFormat($itemNode, '\\'.Decoder::class.'::decodeStream($stream, $v[0], $v[1])'),
                    $denormalize => $this->generateCheckedScalarValue($itemNode, '$v'),
                    default => $this->generateValueFormat($itemNode, '$v'),
                };
            } elseif ($itemDecodeFromStream) {
                $itemPhp = $this->providerAccessor($itemNode, $itemContext).'($stream, $v[0], $v[1])';
            } else {
                $itemPhp = $decodeFromStream
                    ? '$readers['.var_export($itemNode->getIdentifier(), true).']($stream, $v[0], $v[1])'
                    : $this->providerAccessor($itemNode, $itemContext).'($v)';
            }

            if ($node->getType()->isIdentifiedBy(TypeIdentifier::ARRAY)) {
                $php .= $this->line('$collection = [];', $context)
                    .$this->line('foreach ($data as $k => $v) {', $context)
                    .$this->line("    \$collection[\$k] = $itemPhp;", $context)
                    .$this->line('}', $context)
                    .$this->line('return $collection;', $context);
            } else {
                $arguments = $decodeFromStream ? '$stream, $data' : '$data';
                $php .= $this->line("\$iterable = static function ($arguments) use ($uses) {", $context)
                    .$this->line('    foreach ($data as $k => $v) {', $context)
                    .$this->line("        yield \$k => $itemPhp;", $context)
                    .$this->line('    }', $context)
                    .$this->line('};', $context)
                    .$this->line("return \$iterable($arguments);", $context);
            }

            --$context['indentation_level'];

            $php .= $this->line('};', $context);

            if (!$this->canBeDecodedWithJsonDecode($itemNode, $keepDecodedCollections)) {
                $php .= $this->generateProviders($itemNode, $itemDecodeFromStream, $itemContext);
                $context['providers'] = $itemContext['providers'];
            }

            if ($decodeFromStream && !$itemDecodeFromStream) {
                $php .= $this->generateValidators($itemNode, $context).$this->generateEagerReader($itemNode, $context);
            }

            return $php;
        }

        if ($node instanceof ObjectNode) {
            $arguments = $decodeFromStream ? '$stream, $offset, $length' : '$data';

            $php = $this->line("$providerAccessor = static function ($arguments) use ($uses) {", $context);

            ++$context['indentation_level'];

            if ($valueObjectTransformerId = $this->getValueObjectTransformerId($node->getType()->getClassName())) {
                $data = match (true) {
                    $decodeFromStream => '\\'.Decoder::class.'::decodeStream($stream, $offset, $length)',
                    $denormalize => $this->generateCheckedScalarValue($node, '$data'),
                    default => '$data',
                };
                $php .= $this->line('return $transformers->get('.var_export($valueObjectTransformerId, true).")->reverseTransform($data, \$options);", $context);

                --$context['indentation_level'];

                return $php.$this->line('};', $context);
            }

            $php .= $decodeFromStream ? $this->line('$data = \\'.Splitter::class.'::splitDict($stream, $offset, $length);', $context) : '';

            if ($decodeFromStream) {
                $php .= $this->line('return $instantiator->instantiate(\\'.$node->getType()->getClassName().'::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {', $context)
                    .$this->line('    foreach ($data as $k => $v) {', $context)
                    .$this->line('        match ($k) {', $context);

                foreach ($node->getProperties() as $streamedName => $property) {
                    $propertyValuePhp = $this->canBeDecodedWithJsonDecode($property['value'], $keepDecodedCollections)
                        ? $this->generateValueFormat($property['value'], '\\'.Decoder::class.'::decodeStream($stream, $v[0], $v[1])')
                        : $this->providerAccessor($property['value'], $context).'($stream, $v[0], $v[1])';

                    $php .= $this->line('            '.var_export($streamedName, true).' => $object->'.$property['name'].' = '.$property['accessor']($propertyValuePhp).',', $context);
                }

                $php .= $this->line('            default => \\'.Decoder::class.'::validateStream($stream, $v[0], $v[1]),', $context)
                    .$this->line('        };', $context)
                    .$this->line('    }', $context)
                    .$this->line('});', $context);
            } else {
                if ($denormalize) {
                    $php .= $this->line('if (!$data instanceof \\stdClass) {', $context)
                        .$this->line('    '.$this->generateUnexpectedValueThrow($node, '$data').';', $context)
                        .$this->line('}', $context)
                        .$this->line('$data = (array) $data;', $context);
                }

                $php .= $this->line($denormalize ? '$object = (new \\ReflectionClass(\\'.$node->getType()->getClassName().'::class))->newInstanceWithoutConstructor();' : '$object = new \\'.$node->getType()->getClassName().'();', $context);

                foreach ($node->getProperties() as $streamedName => $property) {
                    $quotedStreamedName = var_export($streamedName, true);
                    // denormalizers set null values, string readers skip them
                    [$isSetPhp, $propertyValuePhp] = $this->canBeDecodedWithJsonDecode($property['value'], $keepDecodedCollections)
                        ? ($denormalize ? ["\\array_key_exists($quotedStreamedName, \$data)", $this->generateCheckedScalarValue($property['value'], "\$data[$quotedStreamedName]")] : ["isset(\$data[$quotedStreamedName])", "\$data[$quotedStreamedName]"])
                        : ["\\array_key_exists($quotedStreamedName, \$data)", $this->providerAccessor($property['value'], $context)."(\$data[$quotedStreamedName])"];

                    $php .= $this->line("if ($isSetPhp) {", $context)
                        .$this->line('    $value = '.$property['accessor']($propertyValuePhp).';', $context)
                        .$this->line('    try {', $context)
                        .$this->line('        $object->{'.var_export($property['name'], true).'} = $value;', $context)
                        .$this->line('    } catch (\\TypeError $e) {', $context)
                        .$this->line('        throw new \\'.UnexpectedValueException::class.'($e->getMessage(), 0, $e);', $context)
                        .$this->line('    }', $context)
                        .$this->line('}', $context);
                }

                $php .= $this->line('return $object;', $context);
            }

            --$context['indentation_level'];

            $php .= $this->line('};', $context);

            foreach ($node->getProperties() as $streamedName => $property) {
                if (!$this->canBeDecodedWithJsonDecode($property['value'], $keepDecodedCollections)) {
                    $php .= $this->generateProviders($property['value'], $decodeFromStream, $context);
                }
            }

            return $php;
        }

        throw new LogicException(\sprintf('Unexpected "%s" data model node.', $node::class));
    }

    private function generateValueFormat(DataModelNodeInterface $node, string $accessor): string
    {
        if ($node instanceof BackedEnumNode) {
            /** @var ObjectType $type */
            $type = $node->getType();

            return '\\'.$type->getClassName()."::from($accessor)";
        }

        if ($node instanceof ScalarNode) {
            /** @var BuiltinType $type */
            $type = $node->getType();

            return match (true) {
                TypeIdentifier::NULL === $type->getTypeIdentifier() => 'null',
                TypeIdentifier::OBJECT === $type->getTypeIdentifier() => "(object) $accessor",
                default => $accessor,
            };
        }

        return $accessor;
    }

    private function generateCheckedScalarValue(DataModelNodeInterface $node, string $accessor): string
    {
        return "(null === $accessor || \\is_scalar($accessor) ? ".$this->generateValueFormat($node, $accessor).' : '.$this->generateUnexpectedValueThrow($node, $accessor).')';
    }

    private function generateUnexpectedValueThrow(DataModelNodeInterface $node, string $accessor): string
    {
        return 'throw new \\'.UnexpectedValueException::class."(\\sprintf('Unexpected \"%s\" value for \"%s\".', \\get_debug_type($accessor), ".var_export($node->getIdentifier(), true).'))';
    }

    private function generateCompositeNodeItemCondition(DataModelNodeInterface $node, string $accessor, bool $denormalize): string
    {
        $type = $node->getType();

        if ($type->isIdentifiedBy(TypeIdentifier::NULL)) {
            return "null === $accessor";
        }

        if ($type->isIdentifiedBy(TypeIdentifier::TRUE)) {
            return "true === $accessor";
        }

        if ($type->isIdentifiedBy(TypeIdentifier::FALSE)) {
            return "false === $accessor";
        }

        if ($type->isIdentifiedBy(TypeIdentifier::MIXED)) {
            return 'true';
        }

        if ($type instanceof CollectionType) {
            if ($denormalize) {
                return $this->generateCollectionCondition($type, $accessor);
            }

            return $type->isList() ? "\\is_array($accessor) && \\array_is_list($accessor)" : "\\is_array($accessor)";
        }

        while ($type instanceof WrappingTypeInterface) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof BackedEnumType) {
            return '\\is_'.$type->getBackingType()->getTypeIdentifier()->value."($accessor)";
        }

        if ($node instanceof ObjectNode && $valueObjectTransformerId = $this->getValueObjectTransformerId($node->getType()->getClassName())) {
            $valueObjectTransformer = $this->transformers->get($valueObjectTransformerId);
            $typeIdentifier = $valueObjectTransformer::getStreamValueType()->getTypeIdentifier();

            return match ($typeIdentifier) {
                TypeIdentifier::INT => "\\is_int($accessor)",
                TypeIdentifier::FLOAT => "\\is_float($accessor)",
                TypeIdentifier::BOOL => "\\is_bool($accessor)",
                TypeIdentifier::TRUE => "true === $accessor",
                TypeIdentifier::FALSE => "false === $accessor",
                TypeIdentifier::STRING => "\\is_string($accessor)",
                TypeIdentifier::NULL => "null === $accessor",
                default => throw new LogicException(\sprintf('Expected "%s" stream value type to be one of "%s", but got "%s".', $valueObjectTransformer::class, implode('", "', ['int', 'float', 'bool', 'true', 'false', 'string', 'null']), $typeIdentifier->value)),
            };
        }

        if ($type instanceof ObjectType) {
            return $denormalize ? "$accessor instanceof \\stdClass" : "\\is_array($accessor)";
        }

        if ($type instanceof BuiltinType) {
            return '\\is_'.$type->getTypeIdentifier()->value."($accessor)";
        }

        throw new LogicException(\sprintf('Unexpected "%s" type.', $type::class));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function providerAccessor(DataModelNodeInterface $node, array $context): string
    {
        return (($context['denormalize'] ?? false) ? '$denormalizers' : '$providers').'['.var_export($node->getIdentifier(), true).']';
    }

    private function generateCollectionCondition(CollectionType $type, string $accessor): string
    {
        $keyType = $type->getCollectionKeyType();

        return match (true) {
            $this->hasIntKeys($type) => "\\is_array($accessor)",
            $keyType instanceof BuiltinType && TypeIdentifier::STRING === $keyType->getTypeIdentifier() => "$accessor instanceof \\stdClass",
            default => "\\is_array($accessor) || $accessor instanceof \\stdClass",
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function generateEagerReader(DataModelNodeInterface $node, array &$context): string
    {
        $identifier = var_export($node->getIdentifier(), true);
        if (isset($context['readers'][$node->getIdentifier()])) {
            return '';
        }
        $context['readers'][$node->getIdentifier()] = true;
        $php = $this->line('$readers['.$identifier.'] = static function ($stream, $offset, $length) use (&$providers, &$denormalizers, &$validators) {', $context)
            .$this->line('    try {', $context)
            .$this->line('        $data = \\'.Decoder::class.'::decodeStream($stream, $offset, $length, false);', $context)
            .$this->line('    } catch (\\'.InvalidStreamException::class.' $e) {', $context)
            .$this->line('        if (\\JSON_ERROR_INVALID_PROPERTY_NAME !== $e->getPrevious()?->getCode()) {', $context)
            .$this->line('            throw $e;', $context)
            .$this->line('        }', $context)
            .$this->line('        return $providers['.$identifier.']($stream, $offset, $length);', $context)
            .$this->line('    }', $context)
            .$this->line('    return $denormalizers['.$identifier.']($validators['.$identifier.']($stream, $offset, $length, $data));', $context)
            .$this->line('};', $context);

        return $php.$this->generateProviders($node, true, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function generateValidators(DataModelNodeInterface $node, array &$context): string
    {
        if ($node instanceof ObjectNode && $node->isMock()) {
            $node = $context['object_nodes'][$node->getIdentifier()];
        }

        if (!$this->isStructured($node) || isset($context['validators'][$node->getIdentifier()])) {
            return '';
        }
        $context['validators'][$node->getIdentifier()] = true;
        $accessor = '$validators['.var_export($node->getIdentifier(), true).']';
        $php = $this->line("$accessor = static function (\$stream, \$offset, \$length, \$data) use (&\$validators) {", $context);
        ++$context['indentation_level'];
        $children = [];

        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $child) {
                $php .= $this->line('if ('.$this->generateCompositeNodeItemCondition($child, '$data', true).') {', $context)
                    .$this->line('    return '.($this->isStructured($child) ? '$validators['.var_export($child->getIdentifier(), true).']($stream, $offset, $length, $data)' : '$data').';', $context)
                    .$this->line('}', $context);
                $children[] = $child;
            }
        } elseif ($node instanceof CollectionNode) {
            $keyType = $node->getType()->getCollectionKeyType();
            $splitMethod = match (true) {
                $this->hasIntKeys($node->getType()) => 'splitList',
                $keyType instanceof BuiltinType && TypeIdentifier::STRING === $keyType->getTypeIdentifier() => 'splitDict',
                default => 'splitCollection',
            };
            $php .= $this->line('if ('.$this->generateCollectionCondition($node->getType(), '$data').') {', $context)
                .$this->line('    foreach (\\'.Splitter::class.'::'.$splitMethod.'($stream, $offset, $length) as $k => $v) {', $context);
            $child = $node->getItemNode();
            if ($this->isStructured($child)) {
                $php .= $this->line('        $validators['.var_export($child->getIdentifier(), true).']($stream, $v[0], $v[1], \\is_array($data) ? $data[$k] : $data->{$k});', $context);
            }
            $php .= $this->line('    }', $context).$this->line('}', $context);
            $children[] = $child;
        } elseif ($node instanceof ObjectNode) {
            $php .= $this->line('if ($data instanceof \\stdClass) {', $context)
                .$this->line('    foreach (\\'.Splitter::class.'::splitDict($stream, $offset, $length) as $k => $v) {', $context);
            $structured = [];
            foreach ($node->getProperties() as $name => $property) {
                if ($this->isStructured($property['value'])) {
                    $structured[$name] = $property['value'];
                }
            }
            if ($structured) {
                $php .= $this->line('        match ($k) {', $context);
                foreach ($structured as $name => $child) {
                    $php .= $this->line('            '.var_export((string) $name, true).' => $validators['.var_export($child->getIdentifier(), true).']($stream, $v[0], $v[1], $data->{$k}),', $context);
                    $children[] = $child;
                }
                $php .= $this->line('            default => null,', $context).$this->line('        };', $context);
            }
            $php .= $this->line('    }', $context).$this->line('}', $context);
        }

        $php .= $this->line('return $data;', $context);
        --$context['indentation_level'];
        $php .= $this->line('};', $context);
        foreach ($children as $child) {
            $php .= $this->generateValidators($child, $context);
        }

        return $php;
    }

    private function hasIntKeys(CollectionType $type): bool
    {
        $keyType = $type->getCollectionKeyType();

        return $keyType instanceof BuiltinType && TypeIdentifier::INT === $keyType->getTypeIdentifier();
    }

    /**
     * @param array<string, ObjectNode> $nodes
     *
     * @return array<string, ObjectNode>
     */
    private function collectObjectNodes(DataModelNodeInterface $node, array &$nodes = []): array
    {
        if ($node instanceof CollectionNode) {
            return $this->collectObjectNodes($node->getItemNode(), $nodes);
        }

        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                $this->collectObjectNodes($n, $nodes);
            }

            return $nodes;
        }

        if (!$node instanceof ObjectNode || $node->isMock() || isset($nodes[$node->getIdentifier()])) {
            return $nodes;
        }

        $nodes[$node->getIdentifier()] = $node;
        foreach ($node->getProperties() as $property) {
            $this->collectObjectNodes($property['value'], $nodes);
        }

        return $nodes;
    }

    private function isStructured(DataModelNodeInterface $node): bool
    {
        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                if ($this->isStructured($n)) {
                    return true;
                }
            }

            return false;
        }

        return $node instanceof CollectionNode || ($node instanceof ObjectNode && !$this->getValueObjectTransformerId($node->getType()->getClassName()));
    }

    /**
     * @param array<string, ObjectNode> $objectNodes
     * @param array<string, true>       $visited
     */
    private function canBeReadAtOnce(DataModelNodeInterface $node, array $objectNodes, array &$visited = []): bool
    {
        if ($node instanceof CollectionNode) {
            return $node->getType()->isIdentifiedBy(TypeIdentifier::ARRAY) && $this->canBeReadAtOnce($node->getItemNode(), $objectNodes, $visited);
        }

        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                if (!$this->canBeReadAtOnce($n, $objectNodes, $visited)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof ScalarNode) {
            return !$node->getType()->isIdentifiedBy(TypeIdentifier::MIXED, TypeIdentifier::ARRAY, TypeIdentifier::ITERABLE, TypeIdentifier::OBJECT);
        }

        if (!$node instanceof ObjectNode || isset($visited[$node->getIdentifier()]) || $this->getValueObjectTransformerId($node->getType()->getClassName())) {
            return true;
        }

        $visited[$node->getIdentifier()] = true;
        foreach (($objectNodes[$node->getIdentifier()] ?? $node)->getProperties() as $property) {
            if (!$this->canBeReadAtOnce($property['value'], $objectNodes, $visited)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines if the $node can be decoded using a simple "json_decode".
     */
    private function canBeDecodedWithJsonDecode(DataModelNodeInterface $node, bool $keepDecodedCollections): bool
    {
        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                if (!$this->canBeDecodedWithJsonDecode($n, $keepDecodedCollections)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof CollectionNode) {
            return $keepDecodedCollections && $this->canBeDecodedWithJsonDecode($node->getItemNode(), $keepDecodedCollections);
        }

        if ($node instanceof ObjectNode) {
            return false;
        }

        if ($node instanceof BackedEnumNode) {
            return false;
        }

        if ($node instanceof ScalarNode) {
            return !$node->getType()->isIdentifiedBy(TypeIdentifier::OBJECT);
        }

        return true;
    }
}
