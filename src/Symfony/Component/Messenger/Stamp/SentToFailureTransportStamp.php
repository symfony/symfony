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
 * Stamp applied when a message is sent to the failure transport.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class SentToFailureTransportStamp implements StampInterface
{
    public function __construct(
        private string $originalReceiverName,
    ) {
    }

    public function getOriginalReceiverName(): string
    {
        return $this->originalReceiverName;
    }
}
