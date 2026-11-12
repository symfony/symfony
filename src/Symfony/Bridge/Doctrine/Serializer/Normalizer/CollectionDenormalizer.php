<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Serializer\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Denormalizes arrays into Doctrine collections.
 *
 * The element type is read from the "value_type" context entry: elements that already have it, as the Serializer passes them, are kept as is, and the other ones are denormalized into it.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class CollectionDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @param class-string<Collection> $collectionClass The collection class built when the target type is an interface
     */
    public function __construct(
        private readonly string $collectionClass = ArrayCollection::class,
    ) {
        if (!is_a($this->collectionClass, Collection::class, true)) {
            throw new InvalidArgumentException(\sprintf('The collection class "%s" must implement "%s".', $this->collectionClass, Collection::class));
        }
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Collection::class => false];
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Collection
    {
        if (!\is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Data expected to be "array", "%s" given.', get_debug_type($data)), $data, ['array'], $context['deserialization_path'] ?? null);
        }

        $valueType = $context['value_type'] ?? null;

        // a refined element, such as "positive-int" or "'draft'|'live'", is validated against its builtin type; the lowest symfony/type-info has no refinements to remove
        if ($valueType instanceof Type && method_exists($valueType, 'withoutRefinements')) { // @phpstan-ignore function.alreadyNarrowedType
            $valueType = $valueType->withoutRefinements();
        }

        $elementClass = self::getElementClass($valueType);
        $keyTypeIdentifiers = self::getKeyTypeIdentifiers($context['key_type'] ?? null) ?? [];
        $collectionClass = $this->resolveCollectionClass($type);
        $collection = new $collectionClass();

        foreach ($data as $key => $value) {
            $path = ($context['deserialization_path'] ?? false) ? \sprintf('%s[%s]', $context['deserialization_path'], $key) : "[$key]";

            self::validateKeyType($keyTypeIdentifiers, $key, $path);

            if ($valueType instanceof Type && !$valueType->accepts($value)) {
                if (null === $elementClass) {
                    throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('The type of the element "%s" must be "%s" ("%s" given).', $key, $valueType, get_debug_type($value)), $value, [(string) $valueType], $path);
                }

                if (!isset($this->denormalizer)) {
                    throw new \BadMethodCallException('Please set a denormalizer before calling denormalize()!');
                }

                $childContext = $context;
                $childContext['deserialization_path'] = $path;
                unset($childContext[AbstractNormalizer::OBJECT_TO_POPULATE]);
                $value = $this->denormalizer->denormalize($value, $elementClass, $format, $childContext);
            }

            $collection->set($key, $value);
        }

        return $collection;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // without a known element type, e.g. for "Collection|Item[]", the elements are left to the other members of the type
        $valueType = $context['value_type'] ?? null;
        if (!is_a($type, Collection::class, true) || !$valueType instanceof Type || $valueType->isIdentifiedBy(TypeIdentifier::MIXED)) {
            return false;
        }

        // an interface is built from the configured collection class, a concrete class must be instantiable without arguments so that the elements can be added one by one
        return class_exists($type) ? self::isInstantiableCollection($type) : is_a($this->collectionClass, $type, true);
    }

    /**
     * @return class-string<Collection>
     */
    private function resolveCollectionClass(string $type): string
    {
        return class_exists($type) && self::isInstantiableCollection($type) ? $type : $this->collectionClass;
    }

    /**
     * Tells whether the class can be instantiated without arguments, so elements can be added to it.
     */
    private static function isInstantiableCollection(string $class): bool
    {
        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            return false;
        }

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            if (!$parameter->isOptional() && !$parameter->isVariadic()) {
                return false;
            }
        }

        return true;
    }

    private static function getElementClass(mixed $valueType): ?string
    {
        // elements that are collections themselves are built before they reach this denormalizer
        if ($valueType instanceof CollectionType) {
            return null;
        }

        while ($valueType instanceof WrappingTypeInterface) {
            $valueType = $valueType->getWrappedType();
        }

        return $valueType instanceof ObjectType ? $valueType->getClassName() : null;
    }

    /**
     * @return list<'int'|'string'>|null The builtin types a key is checked against, null when it cannot be checked
     */
    private static function getKeyTypeIdentifiers(mixed $keyType): ?array
    {
        if (!$keyType instanceof Type) {
            return null;
        }

        // a template key is checked against its bound, and a refined one, such as "positive-int" or "'en'|'de'", against its builtin type
        while ($keyType instanceof WrappingTypeInterface && !$keyType instanceof UnionType) {
            $keyType = $keyType->getWrappedType();
        }

        if ($keyType instanceof UnionType) {
            $typeIdentifiers = [];
            foreach ($keyType->getTypes() as $t) {
                // a member that cannot be checked accepts any key
                if (null === $identifiers = self::getKeyTypeIdentifiers($t)) {
                    return null;
                }

                $typeIdentifiers = [...$typeIdentifiers, ...$identifiers];
            }

            return array_values(array_unique($typeIdentifiers));
        }

        if (!$keyType instanceof BuiltinType) {
            return [];
        }

        // only int and string keys are checked; "mixed", such as an unbound template, cannot be, and any other type, such as "null" or an object, matches no key
        return match ($keyType->getTypeIdentifier()) {
            TypeIdentifier::INT, TypeIdentifier::STRING => [$keyType->getTypeIdentifier()->value],
            TypeIdentifier::MIXED => null,
            default => [],
        };
    }

    /**
     * @param list<string> $typeIdentifiers
     */
    private static function validateKeyType(array $typeIdentifiers, mixed $key, string $path): void
    {
        foreach ($typeIdentifiers as $typeIdentifier) {
            if (('is_'.$typeIdentifier)($key)) {
                return;
            }
        }

        if ($typeIdentifiers) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('The type of the key "%s" must be "%s" ("%s" given).', $key, implode('", "', $typeIdentifiers), get_debug_type($key)), $key, $typeIdentifiers, $path, true);
        }
    }
}
