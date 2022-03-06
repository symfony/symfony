<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

/**
 * Indicates that this argument should be deserialized and (optionally) validated.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Input
{
    public function __construct(
        public readonly ?string $format = null,
        public readonly array $serializationContext = [],
        public readonly array $validationGroups = ['Default']
    ) {
    }
}
