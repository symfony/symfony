<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Marker telling that any batch handlers bound to the envelope should be flushed.
 */
final class FlushBatchHandlersStamp implements NonSendableStampInterface
{
    private $force;

    public function __construct(bool $force)
    {
        $this->force = $force;
    }

    public function force(): bool
    {
        return $this->force;
    }
}
