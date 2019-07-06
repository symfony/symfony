<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport;

use Symfony\Component\Mailer\SentMessage;

/**
 * Stores messages in memory.
 *
 * @author Jan Schädlich <schaedlich.jan@gmail.com>
 */
final class InMemoryTransport extends AbstractTransport
{
    /**
     * @var SentMessage[]
     */
    private $messages = [];

    protected function doSend(SentMessage $message): void
    {
        $this->messages[] = $message;
    }

    public function get(): iterable
    {
        return $this->messages;
    }

    /**
     * Resets the transport and removes all messages.
     */
    public function reset(): void
    {
        $this->messages = [];
    }
}
