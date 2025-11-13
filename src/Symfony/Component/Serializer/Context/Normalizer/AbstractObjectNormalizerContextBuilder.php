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

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * A helper providing autocompletion for available AbstractObjectNormalizer options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
abstract class AbstractObjectNormalizerContextBuilder extends AbstractNormalizerContextBuilder
{
    /**
     * Configures whether to respect the max depth metadata on fields.
     */
    public function withEnableMaxDepth(?bool $enableMaxDepth): static
    {
        return $this->with(AbstractObjectNormalizer::ENABLE_MAX_DEPTH, $enableMaxDepth);
    }

    /**
     * Configures a pattern to keep track of the current depth.
     *
     * Must contain exactly two string placeholders.
     *
     * @throws InvalidArgumentException
     */
    public function withDepthKeyPattern(?string $depthKeyPattern): static
    {
        if (null === $depthKeyPattern) {
            return $this->with(AbstractObjectNormalizer::DEPTH_KEY_PATTERN, null);
        }

        // This will match every occurrences of sprintf specifiers
        $matches = [];
        preg_match_all('/(?<!%)(?:%{2})*%(?<specifier>[a-z])/', $depthKeyPattern, $matches);

        if (2 !== \count($matches['specifier']) || 's' !== $matches['specifier'][0] || 's' !== $matches['specifier'][1]) {
            throw new InvalidArgumentException(\sprintf('The depth key pattern "%s" is not valid. You must set exactly two string placeholders.', $depthKeyPattern));
        }

        return $this->with(AbstractObjectNormalizer::DEPTH_KEY_PATTERN, $depthKeyPattern);
    }

    /**
     * Configures whether verifying types match during denormalization.
     */
    public function withDisableTypeEnforcement(?bool $disableTypeEnforcement): static
    {
        return $this->with(AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT, $disableTypeEnforcement);
    }

    /**
     * Configures whether fields with the value `null` should be output during normalization.
     */
    public function withSkipNullValues(?bool $skipNullValues): static
    {
        return $this->with(AbstractObjectNormalizer::SKIP_NULL_VALUES, $skipNullValues);
    }

    /**
     * Configures whether uninitialized typed class properties should be excluded during normalization.
     */
    public function withSkipUninitializedValues(?bool $skipUninitializedValues): static
    {
        return $this->with(AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES, $skipUninitializedValues);
    }

    /**
     * Configures a callback to allow to set a value for an attribute when the max depth has
     * been reached.
     *
     * If no callback is given, the attribute is skipped. If a callable is
     * given, its return value is used (even if null).
     *
     * The arguments are:
     *
     * - mixed                 $attributeValue value of this field
     * - object                $object         the whole object being normalized
     * - string                $attributeName  name of the attribute being normalized
     * - string                $format         the requested format
     * - array<string, mixed>  $context        the serialization context
     */
    public function withMaxDepthHandler(?callable $maxDepthHandler): static
    {
        return $this->with(AbstractObjectNormalizer::MAX_DEPTH_HANDLER, $maxDepthHandler);
    }

    /**
     * Configures which context key are not relevant to determine which attributes
     * of an object to (de)normalize.
     *
     * @param list<string>|null $excludeFromCacheKeys
     */
    public function withExcludeFromCacheKeys(?array $excludeFromCacheKeys): static
    {
        return $this->with(AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY, $excludeFromCacheKeys);
    }

    /**
     * Configures whether to tell the denormalizer to also populate existing objects on
     * attributes of the main object.
     *
     * Setting this to true is only useful if you also specify the root object
     * in AbstractNormalizer::OBJECT_TO_POPULATE.
     */
    public function withDeepObjectToPopulate(?bool $deepObjectToPopulate): static
    {
        return $this->with(AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE, $deepObjectToPopulate);
    }

    /**
     * Configures whether an empty object should be kept as an object (in
     * JSON: {}) or converted to a list (in JSON: []).
     */
    public function withPreserveEmptyObjects(?bool $preserveEmptyObjects): static
    {
        return $this->with(AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS, $preserveEmptyObjects);
    }
}
