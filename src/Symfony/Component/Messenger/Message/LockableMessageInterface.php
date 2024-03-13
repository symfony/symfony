<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Message;

interface LockableMessageInterface
{
    /**
     * Returns null if you want to force the dispatch of the message.
     */
    public function getKey(): ?string;

    /**
     * Should we release the lock before calling the handler or after.
     */
    public function shouldBeReleasedBeforeHandlerCall(): bool;
}
