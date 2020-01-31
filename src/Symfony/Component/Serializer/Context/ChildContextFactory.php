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
 */
class ChildContextFactory implements ChildContextFactoryInterface
{
    public const ATTRIBUTES = AbstractObjectNormalizer::ATTRIBUTES;

    public function create(array $parentContext, string $attribute, ?string $format = null, array $defaultContext = []): array
    {
        if (isset($parentContext[self::ATTRIBUTES][$attribute])) {
            $parentContext[self::ATTRIBUTES] = $parentContext[self::ATTRIBUTES][$attribute];
        } else {
            unset($parentContext[self::ATTRIBUTES]);
        }

        return $parentContext;
    }
}
