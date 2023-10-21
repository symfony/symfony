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

use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;

/**
 * Marker stamp for a received message.
 *
 * This is mainly used by the `SendMessageMiddleware` middleware to identify
 * a message should not be sent if it was just received.
 *
 * @see SendMessageMiddleware
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
final class ReceivedStamp implements NonSendableStampInterface
{
    private string $transportName;

    public function __construct(string $transportName)
    {
        $this->transportName = $transportName;
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }
}
