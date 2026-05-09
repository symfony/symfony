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

        $providers = $this->generateProviders($dataModel, $decodeFromStream, $context);

        $context['indentation_level'] = 0;

        if ($decodeFromStream) {
            return $this->line('<?php', $context)
                .$this->line('', $context)
                .$this->line('/**', $context)
                .$this->line(' * @return '.$dataModel->getType(), $context)
                .$this->line(' */', $context)
                .$this->line('return static function (mixed $stream, \\'.ContainerInterface::class.' $transformers, \\'.LazyInstantiator::class.' $instantiator, array $options): mixed {', $context)
                .$providers
                .($this->canBeDecodedWithJsonDecode($dataModel, $decodeFromStream)
                    ? $this->line('    return \\'.Decoder::class.'::decodeStream($stream, 0, null);', $context)
                    : $this->line('    return $providers['.$this->quote($dataModel->getIdentifier()).']($stream, 0, null);', $context))
                .$this->line('};', $context);
        }

        return $this->line('<?php', $context)
            .$this->line('', $context)
            .$this->line('/**', $context)
            .$this->line(' * @return '.$dataModel->getType(), $context)
            .$this->line(' */', $context)
            .$this->line('return static function (string|\\Stringable $string, \\'.ContainerInterface::class.' $transformers, \\'.Instantiator::class.' $instantiator, array $options): mixed {', $context)
            .$providers
            .($this->canBeDecodedWithJsonDecode($dataModel, $decodeFromStream)
                ? $this->line('    return \\'.Decoder::class.'::decodeString((string) $string);', $context)
                : $this->line('    return $providers['.$this->quote($dataModel->getIdentifier()).'](\\'.Decoder::class.'::decodeString((string) $string));', $context))
            .$this->line('};', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function generateProviders(DataModelNodeInterface $node, bool $decodeFromStream, array &$context): string
    {
        if ($context['providers'][$node->getIdentifier()] ?? false) {
            return '';
        }

        $context['providers'][$node->getIdentifier()] = true;

        if ($this->canBeDecodedWithJsonDecode($node, $decodeFromStream)) {
            return '';
        }

        if ($node instanceof ScalarNode || $node instanceof BackedEnumNode) {
            $accessor = $decodeFromStream ? '\\'.Decoder::class.'::decodeStream($stream, $offset, $length)' : '$data';
            $arguments = $decodeFromStream ? '$stream, $offset, $length' : '$data';

            return $this->line('$providers['.$this->quote($node->getIdentifier())."] = static function ($arguments) {", $context)
                .$this->line('    return '.$this->generateValueFormat($node, $accessor).';', $context)
                .$this->line('};', $context);
        }

        if ($node instanceof CompositeNode) {
            $php = '';
            foreach ($node->getNodes() as $n) {
                if (!$this->canBeDecodedWithJsonDecode($n, $decodeFromStream)) {
                    $php .= $this->generateProviders($n, $decodeFromStream, $context);
                }
            }

            $arguments = $decodeFromStream ? '$stream, $offset, $length' : '$data';

            $php .= $this->line('$providers['.$this->quote($node->getIdentifier())."] = static function ($arguments) use (\$options, \$transformers, \$instantiator, &\$providers) {", $context);

            ++$context['indentation_level'];

            $php .= $decodeFromStream ? $this->line('$data = \\'.Decoder::class.'::decodeStream($stream, $offset, $length);', $context) : '';

            foreach ($node->getNodes() as $n) {
                $value = $this->canBeDecodedWithJsonDecode($n, $decodeFromStream) ? $this->generateValueFormat($n, '$data') : '$providers['.$this->quote($n->getIdentifier())."]($arguments)";
                $php .= $this->line('if ('.$this->generateCompositeNodeItemCondition($n, '$data').') {', $context)
                    .$this->line("    return $value;", $context)
                    .$this->line('}', $context);
            }

            $php .= $this->line('throw new \\'.UnexpectedValueException::class.'(\\sprintf(\'Unexpected "%s" value for "%s".\', \\get_debug_type($data), '.$this->quote($node->getIdentifier()).'));', $context);

            --$context['indentation_level'];

            return $php.$this->line('};', $context);
        }

        if ($node instanceof CollectionNode) {
            $arguments = $decodeFromStream ? '$stream, $offset, $length' : '$data';

            $php = $this->line('$providers['.$this->quote($node->getIdentifier())."] = static function ($arguments) use (\$options, \$transformers, \$instantiator, &\$providers) {", $context);

            ++$context['indentation_level'];

            $collectionKeyType = $node->getType()->getCollectionKeyType();

            $arguments = $decodeFromStream ? '$stream, $data' : '$data';
            $php .= ($decodeFromStream ? $this->line('$data = \\'.Splitter::class.'::'.($collectionKeyType instanceof BuiltinType && TypeIdentifier::INT === $collectionKeyType->getTypeIdentifier() ? 'splitList' : 'splitDict').'($stream, $offset, $length);', $context) : '')
                .$this->line("\$iterable = static function ($arguments) use (\$options, \$transformers, \$instantiator, &\$providers) {", $context)
                .$this->line('    foreach ($data as $k => $v) {', $context);

            if ($decodeFromStream) {
                $php .= $this->canBeDecodedWithJsonDecode($node->getItemNode(), $decodeFromStream)
                    ? $this->line('        yield $k => '.$this->generateValueFormat($node->getItemNode(), '\\'.Decoder::class.'::decodeStream($stream, $v[0], $v[1]);'), $context)
                    : $this->line('        yield $k => $providers['.$this->quote($node->getItemNode()->getIdentifier()).']($stream, $v[0], $v[1]);', $context);
            } else {
                $php .= $this->canBeDecodedWithJsonDecode($node->getItemNode(), $decodeFromStream)
                    ? $this->line('        yield $k => $v;', $context)
                    : $this->line('        yield $k => $providers['.$this->quote($node->getItemNode()->getIdentifier()).']($v);', $context);
            }

            $php .= $this->line('    }', $context)
                .$this->line('};', $context)
                .$this->line('return '.($node->getType()->isIdentifiedBy(TypeIdentifier::ARRAY) ? "\\iterator_to_array(\$iterable($arguments))" : "\$iterable($arguments)").';', $context);

            --$context['indentation_level'];

            $php .= $this->line('};', $context);

            if (!$this->canBeDecodedWithJsonDecode($node->getItemNode(), $decodeFromStream)) {
                $php .= $this->generateProviders($node->getItemNode(), $decodeFromStream, $context);
            }

            return $php;
        }

        if ($node instanceof ObjectNode) {
            if ($node->isMock()) {
                return '';
            }

            $arguments = $decodeFromStream ? '$stream, $offset, $length' : '$data';

            $php = $this->line('$providers['.$this->quote($node->getIdentifier())."] = static function ($arguments) use (\$options, \$transformers, \$instantiator, &\$providers) {", $context);

            ++$context['indentation_level'];

            if ($valueObjectTransformerId = $this->getValueObjectTransformerId($node->getType()->getClassName())) {
                $data = $decodeFromStream ? '\\'.Decoder::class.'::decodeStream($stream, $offset, $length)' : '$data';
                $php .= $this->line("return \$transformers->get('$valueObjectTransformerId')->reverseTransform($data, \$options);", $context);

                --$context['indentation_level'];

                return $php.$this->line('};', $context);
            }

            $php .= $decodeFromStream ? $this->line('$data = \\'.Splitter::class.'::splitDict($stream, $offset, $length);', $context) : '';

            if ($decodeFromStream) {
                $php .= $this->line('return $instantiator->instantiate(\\'.$node->getType()->getClassName().'::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {', $context)
                    .$this->line('    foreach ($data as $k => $v) {', $context)
                    .$this->line('        match ($k) {', $context);

                foreach ($node->getProperties() as $streamedName => $property) {
                    $propertyValuePhp = $this->canBeDecodedWithJsonDecode($property['value'], $decodeFromStream)
                        ? $this->generateValueFormat($property['value'], '\\'.Decoder::class.'::decodeStream($stream, $v[0], $v[1])')
                        : '$providers['.$this->quote($property['value']->getIdentifier()).']($stream, $v[0], $v[1])';

                    $php .= $this->line("            '$streamedName' => \$object->".$property['name'].' = '.$property['accessor']($propertyValuePhp).',', $context);
                }

                $php .= $this->line('            default => null,', $context)
                    .$this->line('        };', $context)
                    .$this->line('    }', $context)
                    .$this->line('});', $context);
            } else {
                $propertiesValuePhp = '[';
                $separator = '';
                foreach ($node->getProperties() as $streamedName => $property) {
                    $propertyValuePhp = $this->canBeDecodedWithJsonDecode($property['value'], $decodeFromStream)
                        ? "\$data['$streamedName'] ?? '_symfony_missing_value'"
                        : "\\array_key_exists('$streamedName', \$data) ? \$providers[".$this->quote($property['value']->getIdentifier())."](\$data['$streamedName']) : '_symfony_missing_value'";
                    $propertiesValuePhp .= "$separator'".$property['name']."' => ".$property['accessor']($propertyValuePhp);
                    $separator = ', ';
                }
                $propertiesValuePhp .= ']';

                $php .= $this->line('return $instantiator->instantiate(\\'.$node->getType()->getClassName()."::class, \\array_filter($propertiesValuePhp, static function (\$v) {", $context)
                    .$this->line('    return \'_symfony_missing_value\' !== $v;', $context)
                    .$this->line('}));', $context);
            }

            --$context['indentation_level'];

            $php .= $this->line('};', $context);

            foreach ($node->getProperties() as $streamedName => $property) {
                if (!$this->canBeDecodedWithJsonDecode($property['value'], $decodeFromStream)) {
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

    private function generateCompositeNodeItemCondition(DataModelNodeInterface $node, string $accessor): string
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
            return "\\is_array($accessor)";
        }

        if ($type instanceof BuiltinType) {
            return '\\is_'.$type->getTypeIdentifier()->value."($accessor)";
        }

        throw new LogicException(\sprintf('Unexpected "%s" type.', $type::class));
    }

    private function quote(string $identifier): string
    {
        return \sprintf("'%s'", addcslashes($identifier, "'"));
    }

    /**
     * Determines if the $node can be decoded using a simple "json_decode".
     */
    private function canBeDecodedWithJsonDecode(DataModelNodeInterface $node, bool $decodeFromStream): bool
    {
        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                if (!$this->canBeDecodedWithJsonDecode($n, $decodeFromStream)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof CollectionNode) {
            if ($decodeFromStream) {
                return false;
            }

            return $this->canBeDecodedWithJsonDecode($node->getItemNode(), $decodeFromStream);
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
