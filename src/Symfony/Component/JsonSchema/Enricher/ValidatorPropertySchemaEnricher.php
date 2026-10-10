<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Enricher;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\DialectKeywords;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Hostname;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Time;
use Symfony\Component\Validator\Constraints\Ulid;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;

/**
 * Translates the validator constraints of a property into JSON Schema keywords.
 *
 * @experimental
 */
final class ValidatorPropertySchemaEnricher implements PropertySchemaEnricherInterface
{
    public function __construct(
        private readonly MetadataFactoryInterface $metadataFactory,
    ) {
    }

    public function enrich(PropertySchema $property, Configuration $config): PropertySchema
    {
        if (!$this->metadataFactory->hasMetadataFor($property->class) || !($metadata = $this->metadataFactory->getMetadataFor($property->class)) instanceof ClassMetadataInterface) {
            return $property;
        }

        $schema = $property->schema;
        $required = $property->required;

        foreach ($this->getConstraints($metadata, $property->property, $this->getGroups($metadata, $config)) as $constraint) {
            if (($constraint instanceof NotBlank && !$constraint->allowNull) || $constraint instanceof NotNull) {
                $required = true;
            }

            $schema = $this->apply($constraint, $schema, $config->dialect);
        }

        return $property->withSchema($schema)->withRequired($required);
    }

    /**
     * @return list<string>
     */
    private function getGroups(ClassMetadataInterface $metadata, Configuration $config): array
    {
        return $config->getValidationGroupNames()
            ?? ($metadata->hasGroupSequence() ? self::flattenGroups($metadata->getGroupSequence()->groups) : [Constraint::DEFAULT_GROUP]);
    }

    /**
     * @param array<string|GroupSequence|array<mixed>> $groups
     *
     * @return list<string>
     */
    private static function flattenGroups(array $groups): array
    {
        $flattened = [];
        foreach ($groups as $group) {
            $flattened = [...$flattened, ...match (true) {
                $group instanceof GroupSequence => self::flattenGroups($group->groups),
                \is_array($group) => self::flattenGroups($group),
                default => [$group],
            }];
        }

        return array_values(array_unique($flattened));
    }

    /**
     * @param list<string> $groups
     *
     * @return list<Constraint>
     */
    private function getConstraints(ClassMetadataInterface $metadata, string $property, array $groups): array
    {
        $constraints = [];

        foreach ($metadata->getPropertyMetadata($property) as $propertyMetadata) {
            foreach ($groups as $group) {
                foreach ($propertyMetadata->findConstraints($group) as $constraint) {
                    $nested = $constraint instanceof Sequentially || $constraint instanceof Compound
                        ? array_filter($constraint->getNestedConstraints(), static fn (Constraint $nested): bool => \in_array($group, $nested->groups, true))
                        : [$constraint];

                    foreach ($nested as $candidate) {
                        $constraints[spl_object_id($candidate)] = $candidate;
                    }
                }
            }
        }

        return array_values($constraints);
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function apply(Constraint $constraint, array $schema, Dialect $dialect): array
    {
        return match (true) {
            $constraint instanceof Length => [...$schema, ...array_filter(['minLength' => $constraint->min, 'maxLength' => $constraint->max], self::isSet(...))],
            $constraint instanceof Count => [...$schema, ...array_filter(['minItems' => $constraint->min, 'maxItems' => $constraint->max], self::isSet(...))],
            $constraint instanceof Range => [...$schema, ...array_filter(['minimum' => $constraint->min, 'maximum' => $constraint->max], self::isNumber(...))],
            $constraint instanceof GreaterThan => self::isNumber($constraint->value) && null === $constraint->propertyPath ? [...$schema, ...$dialect->exclusiveMinimum($constraint->value)] : $schema,
            $constraint instanceof GreaterThanOrEqual => self::isNumber($constraint->value) && null === $constraint->propertyPath ? [...$schema, 'minimum' => $constraint->value] : $schema,
            $constraint instanceof LessThan => self::isNumber($constraint->value) && null === $constraint->propertyPath ? [...$schema, ...$dialect->exclusiveMaximum($constraint->value)] : $schema,
            $constraint instanceof LessThanOrEqual => self::isNumber($constraint->value) && null === $constraint->propertyPath ? [...$schema, 'maximum' => $constraint->value] : $schema,
            $constraint instanceof Regex => $constraint->match && null !== ($pattern = $constraint->getHtmlPattern()) ? [...$schema, 'pattern' => '^('.$pattern.')$'] : $schema,
            $constraint instanceof Choice => $this->applyChoice($constraint, $schema),
            $constraint instanceof Unique => [...$schema, 'uniqueItems' => true],
            $constraint instanceof Email => [...$schema, 'format' => 'email'],
            $constraint instanceof Url => [...$schema, 'format' => 'uri'],
            $constraint instanceof Uuid => [...$schema, 'format' => 'uuid'],
            $constraint instanceof Ulid => [...$schema, 'format' => 'ulid'],
            $constraint instanceof Hostname => [...$schema, 'format' => 'hostname'],
            $constraint instanceof Ip => match ($constraint->version) {
                Ip::V4 => [...$schema, 'format' => 'ipv4'],
                Ip::V6 => [...$schema, 'format' => 'ipv6'],
                default => $schema,
            },
            $constraint instanceof Date => [...$schema, 'format' => 'date'],
            $constraint instanceof DateTime => [...$schema, 'format' => 'date-time'],
            $constraint instanceof Time => [...$schema, 'format' => 'time'],
            default => $schema,
        };
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyChoice(Choice $constraint, array $schema): array
    {
        if (!\is_array($constraint->choices) || !$constraint->match) {
            return $schema;
        }

        $choices = array_values($constraint->choices);

        if (!$constraint->multiple) {
            return DialectKeywords::enum($schema, $choices);
        }

        return [
            ...$schema,
            'items' => [...$schema['items'] ?? [], 'enum' => $choices],
            ...array_filter(['minItems' => $constraint->min, 'maxItems' => $constraint->max], self::isSet(...)),
        ];
    }

    private static function isSet(mixed $value): bool
    {
        return null !== $value;
    }

    private static function isNumber(mixed $value): bool
    {
        return \is_int($value) || \is_float($value);
    }
}
