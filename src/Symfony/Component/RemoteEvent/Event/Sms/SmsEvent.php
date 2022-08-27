<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RemoteEvent\Event\Sms;

use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
final class SmsEvent extends RemoteEvent
{
    public const FAILED = 'failed';
    public const DELIVERED = 'delivered';

    private string $phone = '';

    public function setRecipientPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getRecipientPhone(): string
    {
        return $this->phone;
    }
}
