<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final class ObjectDummyWithContextAttribute
{
    public function __construct(
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'm-d-Y'])]
        #[SerializedName('property_with_serialized_name')]
        public \DateTimeImmutable $propertyWithSerializedName,

        #[Context([DateTimeNormalizer::FORMAT_KEY => 'm-d-Y'])]
        public \DateTimeImmutable $propertyWithoutSerializedName,
    ) {
    }
}
