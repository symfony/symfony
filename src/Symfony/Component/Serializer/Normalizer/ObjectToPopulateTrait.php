<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

trait ObjectToPopulateTrait
{
    /**
     * Extract the `object_to_populate` field from the context if it exists
     * and is an instance of the provided $class.
     *
     * @param string      $class The class the object should be
     * @param string|null $key   They in which to look for the object to populate.
     *                           Keeps backwards compatibility with `AbstractNormalizer`.
     *
     * @return object|null an object if things check out, null otherwise
     */
    protected function extractObjectToPopulate($class, array $context, $key = null)
    {
        $key = $key ?? AbstractNormalizer::OBJECT_TO_POPULATE;

        if (isset($context[$key]) && \is_object($context[$key]) && $context[$key] instanceof $class) {
            return $context[$key];
        }

        return null;
    }
}
