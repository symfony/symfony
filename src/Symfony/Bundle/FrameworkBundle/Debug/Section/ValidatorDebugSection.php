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
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\GenericMetadata;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Mapping\TraversalStrategy;
use Symfony\Component\Validator\ValidatorBuilder;

/**
 * The "Validator" tab of the interactive "debug" command.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class ValidatorDebugSection extends AbstractDebugSection
{
    use MappedClassesTrait;

    /**
     * @param list<class-string> $candidateClasses
     */
    public function __construct(
        private readonly MetadataFactoryInterface $validator,
        private readonly ValidatorBuilder $validatorBuilder,
        private readonly array $candidateClasses = [],
    ) {
    }

    public function getLabel(): string
    {
        return 'Validator';
    }

    public function getShortLabel(): string
    {
        return 'Valid';
    }

    public function describe(DebugItem $item, int $width): string
    {
        $buffer = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $io = new SymfonyStyle(new ArrayInput([]), $buffer);
        $dump = new Dumper($buffer);

        /** @var ClassMetadataInterface $metadata */
        $metadata = $this->validator->getMetadataFor($item->value);

        $io->title($metadata->getClassName());

        if ($metadata->getConstraints()) {
            $io->section('Class constraints');

            foreach ($metadata->getConstraints() as $constraint) {
                $this->renderConstraint($io, $dump, $constraint);
            }
        }

        if ($metadata->getConstrainedProperties()) {
            $io->section('Properties');

            foreach ($metadata->getConstrainedProperties() as $property) {
                $io->writeln(\sprintf('<info>%s</info>', $property));

                foreach ($metadata->getPropertyMetadata($property) as $propertyMetadata) {
                    $this->renderPropertyOptions($io, $propertyMetadata);

                    foreach ($propertyMetadata->getConstraints() as $constraint) {
                        $this->renderConstraint($io, $dump, $constraint, 2);
                    }
                }

                $io->newLine();
            }
        }

        if (!$metadata->getConstraints() && !$metadata->getConstrainedProperties()) {
            $io->text('No validators were found for this class.');
        }

        return $buffer->fetch();
    }

    protected function buildItems(): array
    {
        $items = [];
        foreach ($this->getCandidateClasses() as $class) {
            if (!class_exists($class)) {
                continue;
            }

            try {
                /** @var ClassMetadataInterface $metadata */
                $metadata = $this->validator->getMetadataFor($class);
            } catch (\Throwable) {
                continue;
            }

            if (!$metadata->getConstraints() && !$metadata->getConstrainedProperties()) {
                continue;
            }

            $items[] = new DebugItem('class', $class, $class, searchText: $this->buildHaystack($metadata));
        }

        return $items;
    }

    /**
     * @return list<class-string>
     */
    private function getCandidateClasses(): array
    {
        $classes = array_fill_keys($this->candidateClasses, true);

        foreach ($this->getMappedClasses($this->validatorBuilder->getLoaders()) as $class) {
            $classes[$class] = true;
        }

        $classes = array_keys($classes);
        sort($classes);

        return $classes;
    }

    private function buildHaystack(ClassMetadataInterface $metadata): string
    {
        $haystack = [];

        foreach ($metadata->getConstraints() as $constraint) {
            $haystack[] = $this->formatConstraint($constraint);
        }

        foreach ($metadata->getConstrainedProperties() as $property) {
            $haystack[] = $property;

            foreach ($metadata->getPropertyMetadata($property) as $propertyMetadata) {
                $haystack[] = $this->formatSearchValue($this->getPropertyOptions($propertyMetadata));

                foreach ($propertyMetadata->getConstraints() as $constraint) {
                    $haystack[] = $this->formatConstraint($constraint);
                }
            }
        }

        return implode("\n", array_filter($haystack));
    }

    private function formatConstraint(Constraint $constraint): string
    {
        return implode("\n", array_filter([
            $constraint::class,
            $this->getShortClassName($constraint::class),
            implode("\n", $constraint->groups),
            $this->formatSearchValue($this->getConstraintOptions($constraint)),
        ]));
    }

    private function renderConstraint(SymfonyStyle $io, Dumper $dump, Constraint $constraint, int $indent = 0): void
    {
        $prefix = str_repeat(' ', $indent);

        $io->writeln(\sprintf('%s<comment>%s</comment>', $prefix, $this->getShortClassName($constraint::class)));
        $io->writeln(\sprintf('%s  class: %s', $prefix, $constraint::class));
        $io->writeln(\sprintf('%s  groups: %s', $prefix, implode(', ', $constraint->groups)));

        $options = $this->getConstraintOptions($constraint);
        if (!$options) {
            return;
        }

        $io->writeln(\sprintf('%s  options:', $prefix));
        foreach ($options as $name => $value) {
            $io->writeln(\sprintf('%s    %s: %s', $prefix, $name, $this->indentDump($dump($value), $indent + 6)));
        }
    }

    private function renderPropertyOptions(SymfonyStyle $io, MetadataInterface $metadata): void
    {
        foreach ($this->getPropertyOptions($metadata) as $name => $value) {
            $io->writeln(\sprintf('  %s: %s', $name, $value));
        }
    }

    /**
     * @return array<string, string>
     */
    private function getPropertyOptions(MetadataInterface $metadata): array
    {
        $options = [];

        if (CascadingStrategy::CASCADE === $metadata->getCascadingStrategy()) {
            $options['cascade'] = 'yes';
        }

        if (TraversalStrategy::TRAVERSE === $metadata->getTraversalStrategy()) {
            $options['traversal'] = 'traverse';
        } elseif (TraversalStrategy::IMPLICIT === $metadata->getTraversalStrategy()) {
            $options['traversal'] = 'implicit';
        }

        if ($metadata instanceof GenericMetadata) {
            $autoMapping = match ($metadata->getAutoMappingStrategy()) {
                AutoMappingStrategy::ENABLED => 'enabled',
                AutoMappingStrategy::DISABLED => 'disabled',
                AutoMappingStrategy::NONE => null,
            };

            if (null !== $autoMapping) {
                $options['auto mapping'] = $autoMapping;
            }
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function getConstraintOptions(Constraint $constraint): array
    {
        $options = [];

        foreach (array_keys(get_object_vars($constraint)) as $propertyName) {
            if ('groups' === $propertyName || 'payload' === $propertyName) {
                continue;
            }

            $options[$propertyName] = $constraint->$propertyName;
        }

        if (null !== $constraint->payload) {
            $options['payload'] = $constraint->payload;
        }

        ksort($options);

        return $options;
    }

    private function indentDump(string $dump, int $indent): string
    {
        $dump = trim($dump);

        if (!str_contains($dump, "\n")) {
            return $dump;
        }

        return "\n".str_repeat(' ', $indent).str_replace("\n", "\n".str_repeat(' ', $indent), $dump);
    }

    private function formatSearchValue(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (\is_array($value)) {
            $values = [];
            foreach ($value as $key => $nestedValue) {
                $values[] = (string) $key;
                $values[] = $this->formatSearchValue($nestedValue);
            }

            return implode("\n", array_filter($values));
        }

        if ($value instanceof Constraint) {
            return $this->formatConstraint($value);
        }

        if ($value instanceof \BackedEnum) {
            return implode("\n", [$value::class, $value->name, (string) $value->value]);
        }

        if ($value instanceof \UnitEnum) {
            return implode("\n", [$value::class, $value->name]);
        }

        if (\is_object($value)) {
            $values = [$value::class];

            if ($value instanceof \Stringable) {
                $values[] = (string) $value;
            }

            return implode("\n", $values);
        }

        if (\is_resource($value)) {
            return get_resource_type($value);
        }

        return get_debug_type($value);
    }

    private function getShortClassName(string $class): string
    {
        if (false === $position = strrpos($class, '\\')) {
            return $class;
        }

        return substr($class, $position + 1);
    }
}
