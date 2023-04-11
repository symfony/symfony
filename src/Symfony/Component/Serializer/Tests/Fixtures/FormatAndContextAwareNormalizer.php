<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class FormatAndContextAwareNormalizer extends ObjectNormalizer
{
    protected function isAllowedAttribute($classOrObject, string $attribute, string $format = null, array $context = []): bool
    {
        return \in_array($attribute, ['foo', 'bar']) && 'foo_and_bar_included' === $format;
    }
}
