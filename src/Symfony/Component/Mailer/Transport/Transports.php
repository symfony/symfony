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

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Transports implements TransportInterface
{
    private $transports;
    private $default;

    /**
     * @param TransportInterface[] $transports
     */
    public function __construct(iterable $transports)
    {
        $this->transports = [];
        foreach ($transports as $name => $transport) {
            if (null === $this->default) {
                $this->default = $transport;
            }
            $this->transports[$name] = $transport;
        }

        if (!$this->transports) {
            throw new LogicException(sprintf('"%s" must have at least one transport configured.', __CLASS__));
        }
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        /** @var Message $message */
        if (RawMessage::class === \get_class($message) || !$message->getHeaders()->has('X-Transport')) {
            return $this->default->send($message, $envelope);
        }

        $headers = $message->getHeaders();
        $transport = $headers->get('X-Transport')->getBody();
        $headers->remove('X-Transport');

        if (!isset($this->transports[$transport])) {
            throw new InvalidArgumentException(sprintf('The "%s" transport does not exist (available transports: "%s").', $transport, implode('", "', array_keys($this->transports))));
        }

        return $this->transports[$transport]->send($message, $envelope);
    }

    public function __toString(): string
    {
        return '['.implode(',', array_keys($this->transports)).']';
    }
}
