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

use Symfony\Component\Messenger\Handler\HandlerDescriptor;

/**
 * Marker telling that ack should not be done automatically for this message.
 */
final class NoAutoAckStamp implements NonSendableStampInterface
{
    public function __construct(
        private HandlerDescriptor $handlerDescriptor,
    ) {
    }

    public function getHandlerDescriptor(): HandlerDescriptor
    {
        return $this->handlerDescriptor;
    }
}
