<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context;

use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * Create a child context with cache_key during serialization/deserialization or instantiation process.
 *
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class ObjectChildContextFactory extends ChildContextFactory
{
    public const EXCLUDE_FROM_CACHE_KEY = AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY;
    public const IGNORED_ATTRIBUTES = AbstractObjectNormalizer::IGNORED_ATTRIBUTES;

    public function create(array $parentContext, string $attribute, ?string $format = null, array $defaultContext = []): array
    {
        $parentContext = parent::create($parentContext, $attribute, $format, $defaultContext);
        $parentContext['cache_key'] = $this->getAttributesCacheKey($parentContext, $format, $defaultContext);

        return $parentContext;
    }

    /**
     * Builds the cache key for the attributes cache.
     *
     * The key must be different for every option in the context that could change which attributes should be handled.
     *
     * @return bool|string
     */
    private function getAttributesCacheKey(array $context, ?string $format = null, array $defaultContext = [])
    {
        foreach ($context[self::EXCLUDE_FROM_CACHE_KEY] ?? $defaultContext[self::EXCLUDE_FROM_CACHE_KEY] ?? [] as $key) {
            unset($context[$key]);
        }
        unset($context[self::EXCLUDE_FROM_CACHE_KEY]);
        unset($context['cache_key']); // avoid artificially different keys

        try {
            return md5($format.serialize([
                    'context' => $context,
                    'ignored' => $context[self::IGNORED_ATTRIBUTES] ?? $defaultContext[self::IGNORED_ATTRIBUTES] ?? [],
                ]));
        } catch (\Exception $exception) {
            // The context cannot be serialized, skip the cache
            return false;
        }
    }
}
