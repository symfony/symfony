<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Channel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Channel\AbstractChannel;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class AbstractChannelTest extends TestCase
{
    public function testChannelCannotBeConstructedWithoutTransportAndBus()
    {
        $this->expectException(LogicException::class);

        new DummyChannel();
    }
}

class DummyChannel extends AbstractChannel
{
    public function notify(Notification $notification, RecipientInterface $recipient, string $transportName = null): void
    {
        return;
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return false;
    }
}
