<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RemoteEvent\Event\Mailer;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
final class MailerDeliveryEvent extends AbstractMailerEvent
{
    public const RECEIVED = 'received';
    public const DROPPED = 'dropped';
    public const DELIVERED = 'delivered';
    public const DEFERRED = 'deferred';
    public const BOUNCE = 'bounce';

    private string $reason = '';

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
