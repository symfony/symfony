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
 */
class CollectionEnvelope
{
    /** @param T $item */
    public function addItem(mixed $item): void
    {
    }

    /** @param T $item */
    public function removeItem(mixed $item): void
    {
    }
}
