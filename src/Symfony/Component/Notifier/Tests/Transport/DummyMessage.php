<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Transport;

use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class DummyMessage implements MessageInterface
{
    public function getRecipientId(): ?string
    {
        return 'recipient_id';
    }

    public function getSubject(): string
    {
        return 'subject';
    }

    public function getOptions(): ?MessageOptionsInterface
    {
        return null;
    }

    public function getTransport(): ?string
    {
        return null;
    }
}
