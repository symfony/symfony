<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * When it holds the message of its envelope, serialize() writes the stamps first: the message becomes a reference to this copy.
 */
class DummyStampHoldingAnObject implements StampInterface
{
    public function __construct(
        public readonly object $object,
    ) {
    }
}
