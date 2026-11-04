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

/**
 * @template T
 * @template U
 *
 * @extends CollectionEnvelope<U>
 */
class InheritedCollectionEnvelope extends CollectionEnvelope
{
    /** @var T */
    public mixed $extra;

    public function getItem(): mixed
    {
        return [];
    }
}
