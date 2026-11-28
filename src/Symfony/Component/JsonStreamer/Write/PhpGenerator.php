<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Write;

use Psr\Container\ContainerInterface;
use Symfony\Component\JsonStreamer\DataModel\Write\BackedEnumNode;
use Symfony\Component\JsonStreamer\DataModel\Write\CollectionNode;
use Symfony\Component\JsonStreamer\DataModel\Write\CompositeNode;
use Symfony\Component\JsonStreamer\DataModel\Write\DataModelNodeInterface;
use Symfony\Component\JsonStreamer\DataModel\Write\DateTimeNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ScalarNode;
use Symfony\Component\JsonStreamer\Exception\LogicException;
use Symfony\Component\JsonStreamer\Exception\NotEncodableValueException;
use Symfony\Component\JsonStreamer\Exception\RuntimeException;
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Generates PHP code that writes data to JSON stream.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class PhpGenerator
{
    private const JSON_ENCODE_FLAGS = \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;
    private const THROWING_JSON_ENCODE_FLAGS_EXPRESSION = '\\JSON_THROW_ON_ERROR | \\JSON_PRESERVE_ZERO_FRACTION | \\JSON_UNESCAPED_SLASHES | \\JSON_UNESCAPED_UNICODE';
    private const MAX_DEPTH = 512;

    private string $yieldBuffer = '';

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    public function generate(DataModelNodeInterface $dataModel, array $options = [], array $context = []): string
    {
        $context['depth'] = 0;
        $context['indentation_level'] = 1;

        $generators = $this->generateObjectGenerators($dataModel, $options, $context);

        // filter generators to mock only
        $generators = array_intersect_key($generators, $context['mocks'] ?? []);
        $context['generated_generators'] = array_intersect_key($context['generated_generators'] ?? [], $context['mocks'] ?? []);

        $context['indentation_level'] = 2;
        $yields = $this->generateYields($dataModel, $options, $context)
            .$this->flushYieldBuffer($context);

        $context['indentation_level'] = 0;
        $docType = str_replace('*/', '*\/', (string) $dataModel->getType());

        return $this->line('<?php', $context)
            .$this->line('', $context)
            .$this->line('/**', $context)
            .$this->line(' * @param '.$docType.' $data', $context)
            .$this->line(' */', $context)
            .$this->line('return static function (mixed $data, \\'.ContainerInterface::class.' $valueTransformers, array $options): \\Traversable {', $context)
            .implode('', $generators)
            .$this->line('    try {', $context)
            .$yields
            .$this->line('    } catch (\\JsonException $e) {', $context)
            .$this->line('        throw new \\'.NotEncodableValueException::class.'($e->getMessage(), 0, $e);', $context)
            .$this->line('    }', $context)
            .$this->line('};', $context);
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     *
     * @return array<string, string>
     */
    private function generateObjectGenerators(DataModelNodeInterface $node, array $options, array &$context): array
    {
        if ($node instanceof ObjectNode && $node->isMock()) {
            $context['mocks'][$node->getIdentifier()] = true;

            return [];
        }

        if ($context['generated_generators'][$node->getIdentifier()] ?? false) {
            return [];
        }

        if ($node instanceof CollectionNode) {
            return $this->generateObjectGenerators($node->getItemNode(), $options, $context);
        }

        if ($node instanceof CompositeNode) {
            $generators = [];
            foreach ($node->getNodes() as $n) {
                $generators = [
                    ...$generators,
                    ...$this->generateObjectGenerators($n, $options, $context),
                ];
            }

            return $generators;
        }

        if ($node instanceof ObjectNode) {
            $context['generating_generator'] = true;

            ++$context['indentation_level'];
            $yields = $this->generateYields($node->withAccessor('$data'), $options, $context)
                .$this->flushYieldBuffer($context);
            --$context['indentation_level'];

            $generators = [
                $node->getIdentifier() => $this->line('$generators['.var_export($node->getIdentifier(), true).'] = static function ($data, $depth) use ($valueTransformers, $options, &$generators) {', $context)
                    .$this->line('    if ($depth >= '.self::MAX_DEPTH.') {', $context)
                    .$this->line('        throw new \\'.NotEncodableValueException::class.'(\'Maximum stack depth exceeded\');', $context)
                    .$this->line('    }', $context)
                    .$yields
                    .$this->line('};', $context),
            ];

            foreach ($node->getProperties() as $n) {
                $generators = [
                    ...$generators,
                    ...$this->generateObjectGenerators($n, $options, $context),
                ];
            }

            unset($context['generating_generator']);
            $context['generated_generators'][$node->getIdentifier()] = true;

            return $generators;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    private function generateYields(DataModelNodeInterface $dataModelNode, array $options, array $context): string
    {
        $accessor = $dataModelNode->getAccessor();

        // collections in a union cannot be told apart at runtime, so they are encoded as is
        $castToObject = !($context['union_of_collections'] ?? false) && $this->shouldCastToObject($dataModelNode);
        unset($context['union_of_collections']);

        if ($this->canBeEncodedWithJsonEncode($dataModelNode)) {
            return $this->yield($this->encode($castToObject ? "\\array_is_list(\$dict = $accessor) ? (object) \$dict : \$dict" : $accessor, $context, $this->shouldForceObject($dataModelNode)), $context);
        }

        if ($context['depth'] >= self::MAX_DEPTH) {
            return $this->line('throw new '.NotEncodableValueException::class.'(\'Maximum stack depth exceeded\');', $context);
        }

        if ($dataModelNode instanceof ScalarNode) {
            return match (true) {
                TypeIdentifier::NULL === $dataModelNode->getType()->getTypeIdentifier() => $this->yieldInterpolatedString('null', $context),
                TypeIdentifier::BOOL === $dataModelNode->getType()->getTypeIdentifier() => $this->yield("$accessor ? 'true' : 'false'", $context),
                default => $this->yield($this->encode($accessor, $context), $context),
            };
        }

        if ($dataModelNode instanceof BackedEnumNode) {
            return $this->yield($this->encode("{$accessor}->value", $context), $context);
        }

        if ($dataModelNode instanceof DateTimeNode) {
            return $this->yield($this->encode("\$valueTransformers->get('json_streamer.value_transformer.date_time_to_string')->transform({$accessor}, \$options)", $context), $context);
        }

        if ($dataModelNode instanceof CompositeNode) {
            $php = $this->flushYieldBuffer($context);
            foreach ($dataModelNode->getNodes() as $i => $node) {
                $php .= $this->line((0 === $i ? 'if' : '} elseif').' ('.$this->generateCompositeNodeItemCondition($node).') {', $context);

                ++$context['indentation_level'];
                $php .= $this->generateYields($node, $options, ['union_of_collections' => $this->isUnionOfCollections($dataModelNode)] + $context)
                    .$this->flushYieldBuffer($context);
                --$context['indentation_level'];
            }

            return $php
                .$this->flushYieldBuffer($context)
                .$this->line('} else {', $context)
                .$this->line('    throw new \\'.UnexpectedValueException::class."(\\sprintf('Unexpected \"%s\" value.', \get_debug_type($accessor)));", $context)
                .$this->line('}', $context);
        }

        if ($dataModelNode instanceof CollectionNode) {
            if ($dataModelNode->getType()->isIdentifiedBy(TypeIdentifier::ITERABLE) && $this->canItemsBeEncodedWithJsonEncode($dataModelNode)) {
                $php = $this->flushYieldBuffer($context)
                    .$this->line("if (\is_array($accessor)) {", $context);

                ++$context['indentation_level'];
                $php .= $this->yield($this->encode($castToObject ? "\\array_is_list(\$dict = $accessor) ? (object) \$dict : \$dict" : $accessor, $context, $this->shouldForceObject($dataModelNode)), $context)
                    .$this->flushYieldBuffer($context);
                --$context['indentation_level'];

                $php .= $this->line('} else {', $context);

                ++$context['indentation_level'];
                $php .= $this->generateCollectionLoop($dataModelNode, $options, $context)
                    .$this->flushYieldBuffer($context);
                --$context['indentation_level'];

                return $php
                    .$this->line('}', $context);
            }

            return $this->generateCollectionLoop($dataModelNode, $options, $context);
        }

        if ($dataModelNode instanceof ObjectNode) {
            if (isset($context['generated_generators'][$dataModelNode->getIdentifier()]) || $dataModelNode->isMock()) {
                $depthArgument = ($context['generating_generator'] ?? false) ? '$depth + 1' : (string) $context['depth'];

                return $this->flushYieldBuffer($context)
                    .$this->line('yield from $generators['.var_export($dataModelNode->getIdentifier(), true).']('.$accessor.', '.$depthArgument.');', $context);
            }

            ++$context['depth'];

            $php = $this->line('$prefix'.$context['depth'].' = \'\';', $context)
                .$this->yieldInterpolatedString('{', $context);

            $prefixIsCommaForSure = false;

            foreach ($dataModelNode->getProperties() as $name => $propertyNode) {
                // numeric streamed names are int array keys
                $encodedName = json_encode((string) $name, self::JSON_ENCODE_FLAGS);
                if (false === $encodedName) {
                    throw new RuntimeException(\sprintf('Cannot encode "%s"', $name));
                }

                $encodedName = substr($encodedName, 1, -1);

                if ($propertyNode instanceof CompositeNode && $propertyNode->getType() instanceof NullableType) {
                    $nonNullableCompositeParts = array_values(array_filter(
                        $propertyNode->getNodes(),
                        static fn (DataModelNodeInterface $n): bool => !($n instanceof ScalarNode && $n->getType()->isIdentifiedBy(TypeIdentifier::NULL)),
                    ));

                    $propertyNode = 1 === \count($nonNullableCompositeParts)
                        ? $nonNullableCompositeParts[0]
                        : new CompositeNode($propertyNode->getAccessor(), $nonNullableCompositeParts);

                    $php .= $this->flushYieldBuffer($context)
                        .$this->line('if (null === '.$propertyNode->getAccessor().' && ($options[\'include_null_properties\'] ?? false)) {', $context);

                    ++$context['indentation_level'];

                    $php .= $this->yieldInterpolatedString('{$prefix'.$context['depth'].'}', $context, false)
                        .$this->yieldInterpolatedString('"'.$encodedName.'":', $context)
                        .$this->yieldInterpolatedString('null', $context)
                        .$this->flushYieldBuffer($context);

                    if (!$prefixIsCommaForSure && $name !== array_key_last($dataModelNode->getProperties())) {
                        $php .= $this->line('$prefix'.$context['depth'].' = \',\';', $context);
                    }

                    --$context['indentation_level'];

                    $php .= $this->line('}', $context)
                        .$this->flushYieldBuffer($context)
                        .$this->line('if (null !== '.$propertyNode->getAccessor().') {', $context);

                    ++$context['indentation_level'];

                    $php .= $this->yieldInterpolatedString('{$prefix'.$context['depth'].'}', $context, false)
                        .$this->yieldInterpolatedString('"'.$encodedName.'":', $context)
                        .$this->flushYieldBuffer($context)
                        .$this->generateYields($propertyNode, $options, $context)
                        .$this->flushYieldBuffer($context);

                    if (!$prefixIsCommaForSure && $name !== array_key_last($dataModelNode->getProperties())) {
                        $php .= $this->line('$prefix'.$context['depth'].' = \',\';', $context);
                    }

                    --$context['indentation_level'];

                    $php .= $this->line('}', $context);
                } else {
                    $php .= $this->yieldInterpolatedString('{$prefix'.$context['depth'].'}', $context, false)
                        .$this->yieldInterpolatedString('"'.$encodedName.'":', $context)
                        .$this->flushYieldBuffer($context)
                        .$this->generateYields($propertyNode, $options, $context);

                    if (!$prefixIsCommaForSure && $name !== array_key_last($dataModelNode->getProperties())) {
                        $php .= $this->line('$prefix'.$context['depth'].' = \',\';', $context);
                    }

                    $prefixIsCommaForSure = true;
                }
            }

            return $php
                .$this->yieldInterpolatedString('}', $context);
        }

        throw new LogicException(\sprintf('Unexpected "%s" node', $dataModelNode::class));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encode(string $value, array $context, bool $forceObject = false): string
    {
        return "\json_encode($value, ".self::THROWING_JSON_ENCODE_FLAGS_EXPRESSION.($forceObject ? ' | \\JSON_FORCE_OBJECT' : '').', '.(self::MAX_DEPTH - $context['depth']).')';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function yield(string $value, array $context): string
    {
        return $this->flushYieldBuffer($context)
            .$this->line("yield $value;", $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function yieldInterpolatedString(string $string, array $context, bool $escapeDollar = true): string
    {
        $this->yieldBuffer .= addcslashes($string, "\\\"\n\r\t\v\e\f".($escapeDollar ? '$' : ''));

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function flushYieldBuffer(array $context): string
    {
        if ('' === $this->yieldBuffer) {
            return '';
        }

        $yieldBuffer = $this->yieldBuffer;
        $this->yieldBuffer = '';

        return $this->yield('"'.$yieldBuffer.'"', $context);
    }

    private function generateCompositeNodeItemCondition(DataModelNodeInterface $node): string
    {
        $accessor = $node->getAccessor();
        $type = $node->getType();

        if ($type->isIdentifiedBy(TypeIdentifier::NULL, TypeIdentifier::NEVER, TypeIdentifier::VOID)) {
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

        while ($type instanceof WrappingTypeInterface) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof ObjectType) {
            return "$accessor instanceof \\".$type->getClassName();
        }

        if ($type instanceof BuiltinType) {
            return '\\is_'.$type->getTypeIdentifier()->value."($accessor)";
        }

        throw new LogicException(\sprintf('Unexpected "%s" type.', $type::class));
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    private function generateCollectionLoop(CollectionNode $dataModelNode, array $options, array $context): string
    {
        $accessor = $dataModelNode->getAccessor();
        ++$context['depth'];

        $collectionKeyType = $dataModelNode->getType()->getCollectionKeyType();

        if ($collectionKeyType instanceof BuiltinType && TypeIdentifier::INT === $collectionKeyType->getTypeIdentifier()) {
            $php = $this->yieldInterpolatedString('[', $context)
                .$this->flushYieldBuffer($context)
                .$this->line('$prefix'.$context['depth'].' = \'\';', $context)
                .$this->line("foreach ($accessor as ".$dataModelNode->getItemNode()->getAccessor().') {', $context);

            ++$context['indentation_level'];
            $php .= $this->yieldInterpolatedString('{$prefix'.$context['depth'].'}', $context, false)
                .$this->generateYields($dataModelNode->getItemNode(), $options, $context)
                .$this->flushYieldBuffer($context)
                .$this->line('$prefix'.$context['depth'].' = \',\';', $context);

            --$context['indentation_level'];

            return $php
                .$this->line('}', $context)
                .$this->yieldInterpolatedString(']', $context);
        }

        $keyAccessor = $dataModelNode->getKeyNode()->getAccessor();

        $escapedKey = "$keyAccessor = is_int($keyAccessor) ? $keyAccessor : \substr(\json_encode($keyAccessor, ".self::THROWING_JSON_ENCODE_FLAGS_EXPRESSION.'), 1, -1);';

        $php = $this->yieldInterpolatedString('{', $context)
            .$this->flushYieldBuffer($context)
            .$this->line('$prefix'.$context['depth'].' = \'\';', $context)
            .$this->line("foreach ($accessor as $keyAccessor => ".$dataModelNode->getItemNode()->getAccessor().') {', $context);

        ++$context['indentation_level'];
        $php .= $this->line($escapedKey, $context)
            .$this->yieldInterpolatedString('{$prefix'.$context['depth'].'}"{'.$keyAccessor.'}":', $context, false)
            .$this->generateYields($dataModelNode->getItemNode(), $options, $context)
            .$this->flushYieldBuffer($context)
            .$this->line('$prefix'.$context['depth'].' = \',\';', $context);

        --$context['indentation_level'];

        return $php
            .$this->line('}', $context)
            .$this->yieldInterpolatedString('}', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function line(string $line, array $context): string
    {
        return str_repeat('    ', $context['indentation_level']).$line."\n";
    }

    /**
     * Determines if the $node can be encoded using a simple "json_encode".
     */
    private function canBeEncodedWithJsonEncode(DataModelNodeInterface $node, int $depth = 0, bool $splitDictLists = true): bool
    {
        if ($node instanceof CompositeNode) {
            $isUnionOfCollections = $this->isUnionOfCollections($node);

            foreach ($node->getNodes() as $n) {
                if ((!$isUnionOfCollections && $this->shouldCastToObject($n)) || !$this->canBeEncodedWithJsonEncode($n, $depth, $splitDictLists && !$isUnionOfCollections)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof CollectionNode) {
            return $node->getType()->isIdentifiedBy(TypeIdentifier::ARRAY) && $this->canItemsBeEncodedWithJsonEncode($node, $depth, $splitDictLists);
        }

        if (!$node instanceof ScalarNode) {
            return false;
        }

        $type = $node->getType();

        // "null" will be written directly using the "null" string
        // "bool" will be written directly using the "true" or "false" string
        // but it must not prevent any json_encode if nested
        if ($type->isIdentifiedBy(TypeIdentifier::NULL) || $type->isIdentifiedBy(TypeIdentifier::BOOL)) {
            return $depth > 0;
        }

        return true;
    }

    private function canItemsBeEncodedWithJsonEncode(CollectionNode $node, int $depth = 0, bool $splitDictLists = true): bool
    {
        $keyType = $node->getType()->getCollectionKeyType();

        if ($keyType instanceof BuiltinType && TypeIdentifier::INT === $keyType->getTypeIdentifier()) {
            // dicts in a list are encoded one by one, so that "\JSON_FORCE_OBJECT" can be used for each of them
            if ($splitDictLists && ($this->shouldForceObject($node->getItemNode()) || $this->shouldCastToObject($node->getItemNode()))) {
                return false;
            }
        } elseif (!$this->isDict($node)) {
            // keep json_encode() for "int|string" keys, since it writes lists as JSON arrays while the generated loop writes a JSON object
            $splitDictLists = false;
        } elseif ($depth > 0 && $splitDictLists && $this->shouldCastToObject($node)) {
            // only a dict encoded on its own can be cast to an object
            return false;
        }

        return $this->canBeEncodedWithJsonEncode($node->getItemNode(), $depth + 1, $splitDictLists);
    }

    private function shouldCastToObject(DataModelNodeInterface $node): bool
    {
        return $node instanceof CollectionNode && $this->isDict($node) && !$this->canBeEncodedAsObject($node);
    }

    private function isUnionOfCollections(CompositeNode $node): bool
    {
        return 1 < \count(array_filter($node->getNodes(), static fn (DataModelNodeInterface $n): bool => $n instanceof CollectionNode));
    }

    /**
     * Determines if the node contains dicts and if "\JSON_FORCE_OBJECT" can be used to encode it.
     */
    private function shouldForceObject(DataModelNodeInterface $node): bool
    {
        return $this->containsDict($node) && $this->canBeEncodedAsObject($node);
    }

    private function containsDict(DataModelNodeInterface $node): bool
    {
        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                if ($this->containsDict($n)) {
                    return true;
                }
            }

            return false;
        }

        return $node instanceof CollectionNode && $this->isDict($node);
    }

    private function canBeEncodedAsObject(DataModelNodeInterface $node): bool
    {
        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                if (!$this->canBeEncodedAsObject($n)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof CollectionNode) {
            return $this->isDict($node) && $this->canBeEncodedAsObject($node->getItemNode());
        }

        return $node instanceof ScalarNode && $node->getType()->isIdentifiedBy(TypeIdentifier::INT, TypeIdentifier::FLOAT, TypeIdentifier::STRING, TypeIdentifier::BOOL, TypeIdentifier::TRUE, TypeIdentifier::FALSE, TypeIdentifier::NULL);
    }

    private function isDict(CollectionNode $node): bool
    {
        $keyType = $node->getType()->getCollectionKeyType();

        return $keyType instanceof BuiltinType && TypeIdentifier::STRING === $keyType->getTypeIdentifier();
    }
}
