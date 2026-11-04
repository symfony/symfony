<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures\Generic;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

/**
 * @template T
 */
#[DiscriminatorMap(typeProperty: 'kind', mapping: ['base' => DiscriminatedEnvelope::class, 'child' => DiscriminatorChild::class])]
class DiscriminatedEnvelope
{
    /** @var T */
    public mixed $data;
}
