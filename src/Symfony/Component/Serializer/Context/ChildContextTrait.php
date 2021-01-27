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
 * Create a child context during serialization/deserialization process.
 *
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @internal
 */
trait ChildContextTrait
{
    public function createChildContext(array $parentContext, string $attribute, ?string $format = null, array $defaultContext = []): array
    {
        if (isset($parentContext[AbstractObjectNormalizer::ATTRIBUTES][$attribute])) {
            $parentContext[AbstractObjectNormalizer::ATTRIBUTES] = $parentContext[AbstractObjectNormalizer::ATTRIBUTES][$attribute];
        } else {
            unset($parentContext[AbstractObjectNormalizer::ATTRIBUTES]);
        }

        return $parentContext;
    }
}
