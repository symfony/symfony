<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Command\DebugCommand as SerializerDebugCommand;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * The "Serializer" tab of the interactive "debug" command.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class SerializerDebugSection extends AbstractDebugSection
{
    use MappedClassesTrait;

    private const string GROUP_METADATA = 'Metadata';
    private const string GROUP_NAMED_SERIALIZERS = 'Named Serializers';
    private const string GROUP_NORMALIZERS = 'Normalizers';
    private const string GROUP_ENCODERS = 'Encoders';

    /**
     * @param list<object>                                                          $metadataLoaders
     * @param list<array{id: string, class: string, priority: int, built_in: bool}> $normalizers
     * @param list<array{id: string, class: string, priority: int, built_in: bool}> $encoders
     * @param array<string, array<string, mixed>>                                   $namedSerializers
     */
    public function __construct(
        private readonly ClassMetadataFactoryInterface $serializer,
        private readonly array $metadataLoaders = [],
        private readonly array $normalizers = [],
        private readonly array $encoders = [],
        private readonly array $namedSerializers = [],
    ) {
    }

    public function getLabel(): string
    {
        return 'Serializer';
    }

    public function getShortLabel(): string
    {
        return 'Ser';
    }

    public function describe(DebugItem $item, int $width): string
    {
        if ('metadata' === $item->type) {
            $buffer = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);

            (new SerializerDebugCommand($this->serializer))->run(new ArrayInput(['class' => $item->value]), $buffer);

            return $buffer->fetch();
        }

        if (null !== $item->detail) {
            return $item->detail;
        }

        return \sprintf('Serializer item "%s" does not exist.', $item->value);
    }

    protected function buildItems(): array
    {
        $items = [];

        foreach ($this->getMetadataClasses() as $class) {
            $items[] = new DebugItem('metadata', $class, $class, self::GROUP_METADATA);
        }

        foreach ($this->namedSerializers as $name => $config) {
            $items[] = new DebugItem('named_serializer', $name, $name, self::GROUP_NAMED_SERIALIZERS, $this->renderNamedSerializerDetail($name, $config), searchText: implode("\n", array_keys($config)));
        }

        foreach ($this->normalizers as $normalizer) {
            $items[] = new DebugItem('normalizer', $normalizer['id'], $normalizer['class'], self::GROUP_NORMALIZERS, $this->renderTaggedServiceDetail('Normalizer', $normalizer), searchText: implode("\n", [$normalizer['priority'], $normalizer['built_in']]));
        }

        foreach ($this->encoders as $encoder) {
            $items[] = new DebugItem('encoder', $encoder['id'], $encoder['class'], self::GROUP_ENCODERS, $this->renderTaggedServiceDetail('Encoder', $encoder), searchText: implode("\n", [$encoder['priority'], $encoder['built_in']]));
        }

        return $items;
    }

    /**
     * @return list<class-string>
     */
    private function getMetadataClasses(): array
    {
        $metadataClasses = [];
        foreach ($this->getMappedClasses($this->metadataLoaders) as $class) {
            if (!class_exists($class)) {
                continue;
            }

            try {
                $metadata = $this->serializer->getMetadataFor($class);
            } catch (\Throwable) {
                continue;
            }

            if ($this->hasMetadata($metadata)) {
                $metadataClasses[] = $class;
            }
        }

        return $metadataClasses;
    }

    private function hasMetadata(ClassMetadataInterface $metadata): bool
    {
        if (null !== $metadata->getClassDiscriminatorMapping()) {
            return true;
        }

        foreach ($metadata->getAttributesMetadata() as $attributeMetadata) {
            if ($this->hasAttributeMetadata($attributeMetadata)) {
                return true;
            }
        }

        return false;
    }

    private function hasAttributeMetadata(AttributeMetadataInterface $metadata): bool
    {
        return [] !== $metadata->getGroups()
            || null !== $metadata->getMaxDepth()
            || null !== $metadata->getSerializedName()
            || null !== $metadata->getSerializedPath()
            || $metadata->isIgnored()
            || [] !== $metadata->getNormalizationContexts()
            || [] !== $metadata->getDenormalizationContexts();
    }

    /**
     * @param array{id: string, class: string, priority: int, built_in: bool} $service
     */
    private function renderTaggedServiceDetail(string $type, array $service): string
    {
        return $this->describeToBuffer(static function (SymfonyStyle $io) use ($type, $service): void {
            $io->title($service['class']);
            $io->table(['Option', 'Value'], [
                ['Type', $type],
                ['Service ID', $service['id']],
                ['Priority', (string) $service['priority']],
                ['Built-in', $service['built_in'] ? 'yes' : 'no'],
            ]);
        });
    }

    /**
     * @param array<string, mixed> $config
     */
    private function renderNamedSerializerDetail(string $name, array $config): string
    {
        return $this->describeToBuffer(function (SymfonyStyle $io) use ($name, $config): void {
            $io->title($name);

            $rows = [];
            foreach ($config as $key => $value) {
                $rows[] = [$key, $this->formatValue($value)];
            }

            $io->table(['Option', 'Value'], $rows ?: [['Configuration', 'default']]);
        });
    }

    private function formatValue(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (null === $value) {
            return 'null';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) ?: get_debug_type($value);
    }
}
