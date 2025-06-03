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
use Symfony\Component\JsonStreamer\DataModel\Write\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ScalarNode;
use Symfony\Component\JsonStreamer\Exception\LogicException;
use Symfony\Component\JsonStreamer\Exception\NotEncodableValueException;
use Symfony\Component\JsonStreamer\Exception\RuntimeException;
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;
use Symfony\Component\TypeInfo\Type\BuiltinType;
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

        return $this->line('<?php', $context)
            .$this->line('', $context)
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
            if ($node->isMock()) {
                $context['mocks'][$node->getIdentifier()] = true;

                return [];
            }

            $context['generating_generator'] = true;

            ++$context['indentation_level'];
            $yields = $this->generateYields($node->withAccessor('$data'), $options, $context)
                .$this->flushYieldBuffer($context);
            --$context['indentation_level'];

            $generators = [
                $node->getIdentifier() => $this->line('$generators[\''.$node->getIdentifier().'\'] = static function ($data, $depth) use ($valueTransformers, $options, &$generators) {', $context)
                    .$this->line('    if ($depth >= 512) {', $context)
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

        if ($this->canBeEncodedWithJsonEncode($dataModelNode)) {
            return $this->yield($this->encode($accessor, $context), $context);
        }

        if ($context['depth'] >= 512) {
            return $this->line('throw new '.NotEncodableValueException::class.'(\'Maximum stack depth exceeded\');', $context);
        }

        if ($dataModelNode instanceof ScalarNode) {
            return match (true) {
                TypeIdentifier::NULL === $dataModelNode->getType()->getTypeIdentifier() => $this->yieldString('null', $context),
                TypeIdentifier::BOOL === $dataModelNode->getType()->getTypeIdentifier() => $this->yield("$accessor ? 'true' : 'false'", $context),
                default => $this->yield($this->encode($accessor, $context), $context),
            };
        }

        if ($dataModelNode instanceof BackedEnumNode) {
            return $this->yield($this->encode("{$accessor}->value", $context), $context);
        }

        if ($dataModelNode instanceof CompositeNode) {
            $php = $this->flushYieldBuffer($context);
            foreach ($dataModelNode->getNodes() as $i => $node) {
                $php .= $this->line((0 === $i ? 'if' : '} elseif').' ('.$this->generateCompositeNodeItemCondition($node).') {', $context);

                ++$context['indentation_level'];
                $php .= $this->generateYields($node, $options, $context)
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
            ++$context['depth'];

            if ($dataModelNode->getType()->isList()) {
                $php = $this->yieldString('[', $context)
                    .$this->flushYieldBuffer($context)
                    .$this->line('$prefix = \'\';', $context)
                    .$this->line("foreach ($accessor as ".$dataModelNode->getItemNode()->getAccessor().') {', $context);

                ++$context['indentation_level'];
                $php .= $this->yield('$prefix', $context)
                    .$this->generateYields($dataModelNode->getItemNode(), $options, $context)
                    .$this->flushYieldBuffer($context)
                    .$this->line('$prefix = \',\';', $context);

                --$context['indentation_level'];

                return $php
                    .$this->line('}', $context)
                    .$this->yieldString(']', $context);
            }

            $escapedKey = $dataModelNode->getType()->getCollectionKeyType()->isIdentifiedBy(TypeIdentifier::INT)
                ? '$key = is_int($key) ? $key : \substr(\json_encode($key), 1, -1);'
                : '$key = \substr(\json_encode($key), 1, -1);';

            $php = $this->yieldString('{', $context)
                .$this->flushYieldBuffer($context)
                .$this->line('$prefix = \'\';', $context)
                .$this->line("foreach ($accessor as \$key => ".$dataModelNode->getItemNode()->getAccessor().') {', $context);

            ++$context['indentation_level'];
            $php .= $this->line($escapedKey, $context)
                .$this->yield('"{$prefix}\"{$key}\":"', $context)
                .$this->generateYields($dataModelNode->getItemNode(), $options, $context)
                .$this->flushYieldBuffer($context)
                .$this->line('$prefix = \',\';', $context);

            --$context['indentation_level'];

            return $php
                .$this->line('}', $context)
                .$this->yieldString('}', $context);
        }

        if ($dataModelNode instanceof ObjectNode) {
            if (isset($context['generated_generators'][$dataModelNode->getIdentifier()]) || $dataModelNode->isMock()) {
                $depthArgument = ($context['generating_generator'] ?? false) ? '$depth + 1' : (string) $context['depth'];

                return $this->line('yield from $generators[\''.$dataModelNode->getIdentifier().'\']('.$accessor.', '.$depthArgument.');', $context);
            }

            $php = $this->yieldString('{', $context);
            $separator = '';

            ++$context['depth'];

            foreach ($dataModelNode->getProperties() as $name => $propertyNode) {
                $encodedName = json_encode($name);
                if (false === $encodedName) {
                    throw new RuntimeException(\sprintf('Cannot encode "%s"', $name));
                }

                $encodedName = substr($encodedName, 1, -1);

                $php .= $this->yieldString($separator, $context)
                    .$this->yieldString('"', $context)
                    .$this->yieldString($encodedName, $context)
                    .$this->yieldString('":', $context)
                    .$this->generateYields($propertyNode, $options, $context);

                $separator = ',';
            }

            return $php
                .$this->yieldString('}', $context);
        }

        throw new LogicException(\sprintf('Unexpected "%s" node', $dataModelNode::class));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encode(string $value, array $context): string
    {
        return "\json_encode($value, \\JSON_THROW_ON_ERROR, ". 512 - $context['depth'].')';
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
    private function yieldString(string $string, array $context): string
    {
        $this->yieldBuffer .= $string;

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

        return $this->yield("'$yieldBuffer'", $context);
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
     * @param array<string, mixed> $context
     */
    private function line(string $line, array $context): string
    {
        return str_repeat('    ', $context['indentation_level']).$line."\n";
    }

    /**
     * Determines if the $node can be encoded using a simple "json_encode".
     */
    private function canBeEncodedWithJsonEncode(DataModelNodeInterface $node, int $depth = 0): bool
    {
        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                if (!$this->canBeEncodedWithJsonEncode($n, $depth)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof CollectionNode) {
            return $this->canBeEncodedWithJsonEncode($node->getItemNode(), $depth + 1);
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
}
