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
 *
 * @internal
 */
trait ObjectChildContextTrait
{
    use ChildContextTrait {
        createChildContext as parentCreateChildContext;
    }

    public function createChildContext(array $parentContext, string $attribute, ?string $format = null, array $defaultContext = []): array
    {
        $parentContext = $this->parentCreateChildContext($parentContext, $attribute, $format, $defaultContext);
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
        foreach ($context[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY] ?? $defaultContext[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY] ?? [] as $key) {
            unset($context[$key]);
        }
        unset($context[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY]);
        unset($context['cache_key']); // avoid artificially different keys

        try {
            return md5($format.serialize([
                    'context' => $context,
                    'ignored' => $context[AbstractObjectNormalizer::IGNORED_ATTRIBUTES] ?? $defaultContext[AbstractObjectNormalizer::IGNORED_ATTRIBUTES] ?? [],
                ]));
        } catch (\Exception $exception) {
            // The context cannot be serialized, skip the cache
            return false;
        }
    }
}
