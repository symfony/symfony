<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context\Normalizer;

use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * A helper providing autocompletion for available AbstractNormalizer options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
abstract class AbstractNormalizerContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures how many loops of circular reference to allow while normalizing.
     *
     * The value 1 means that when we encounter the same object a
     * second time, we consider that a circular reference.
     *
     * You can raise this value for special cases, e.g. in combination with the
     * max depth setting of the object normalizer.
     *
     * Must be strictly positive.
     *
     * @param positive-int|null $circularReferenceLimit
     */
    public function withCircularReferenceLimit(?int $circularReferenceLimit): static
    {
        return $this->with(AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT, $circularReferenceLimit);
    }

    /**
     * Configures an object to be updated instead of creating a new instance.
     *
     * If you have a nested structure, child objects will be overwritten with
     * new instances unless you set AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE to true.
     */
    public function withObjectToPopulate(?object $objectToPopulate): static
    {
        return $this->with(AbstractNormalizer::OBJECT_TO_POPULATE, $objectToPopulate);
    }

    /**
     * Configures groups containing attributes to (de)normalize.
     *
     * Eg: ['group1', 'group2']
     *
     * @param list<string>|string|null $groups
     */
    public function withGroups(array|string|null $groups): static
    {
        if (null === $groups) {
            return $this->with(AbstractNormalizer::GROUPS, null);
        }

        return $this->with(AbstractNormalizer::GROUPS, (array) $groups);
    }

    /**
     * Configures attributes to (de)normalize.
     *
     * For nested structures, this list needs to reflect the object tree.
     *
     * Eg: ['foo', 'bar', 'object' => ['baz']]
     *
     * @param array<string|array>|null $attributes
     *
     * @throws InvalidArgumentException
     */
    public function withAttributes(?array $attributes): static
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($attributes ?? []), \RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($it as $attribute) {
            if (!\is_string($attribute)) {
                throw new InvalidArgumentException(sprintf('Each attribute must be a string, "%s" given.', get_debug_type($attribute)));
            }
        }

        return $this->with(AbstractNormalizer::ATTRIBUTES, $attributes);
    }

    /**
     * If AbstractNormalizer::ATTRIBUTES are specified, and the source has fields that are not part of that list,
     * configures whether to ignore those attributes or throw an ExtraAttributesException.
     */
    public function withAllowExtraAttributes(?bool $allowExtraAttributes): static
    {
        return $this->with(AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES, $allowExtraAttributes);
    }

    /**
     * Configures an hashmap of classes containing hashmaps of constructor argument => default value.
     *
     * The names need to match the parameter names in the constructor arguments.
     *
     * Eg: [Foo::class => ['foo' => true, 'bar' => 0]]
     *
     * @param array<class-string, array<string, mixed>>|null $defaultContructorArguments
     */
    public function withDefaultContructorArguments(?array $defaultContructorArguments): static
    {
        return $this->with(AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS, $defaultContructorArguments);
    }

    /**
     * Configures an hashmap of field name => callable to normalize this field.
     *
     * The callable is called if the field is encountered with the arguments:
     *
     * - mixed                 $attributeValue value of this field
     * - object                $object         the whole object being normalized
     * - string                $attributeName  name of the attribute being normalized
     * - string                $format         the requested format
     * - array<string, mixed>  $context        the serialization context
     *
     * @param array<string, callable>|null $callbacks
     */
    public function withCallbacks(?array $callbacks): static
    {
        return $this->with(AbstractNormalizer::CALLBACKS, $callbacks);
    }

    /**
     * Configures an handler to call when a circular reference has been detected.
     *
     * If no handler is specified, a CircularReferenceException is thrown.
     *
     * The method will be called with ($object, $format, $context) and its
     * return value is returned as the result of the normalize call.
     */
    public function withCircularReferenceHandler(?callable $circularReferenceHandler): static
    {
        return $this->with(AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER, $circularReferenceHandler);
    }

    /**
     * Configures attributes to be skipped when normalizing an object tree.
     *
     * This list is applied to each element of nested structures.
     *
     * Eg: ['foo', 'bar']
     *
     * Note: The behaviour for nested structures is different from ATTRIBUTES
     * for historical reason. Aligning the behaviour would be a BC break.
     *
     * @param list<string>|null $attributes
     */
    public function withIgnoredAttributes(?array $ignoredAttributes): static
    {
        return $this->with(AbstractNormalizer::IGNORED_ATTRIBUTES, $ignoredAttributes);
    }
}
