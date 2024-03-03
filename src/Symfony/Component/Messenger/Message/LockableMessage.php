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

use Symfony\Component\Lock\Key;

interface LockableMessage
{
    /**
     * Returns null if you want to force the dispatch of the message.
     */
    public function getKey(): ?Key;
}
