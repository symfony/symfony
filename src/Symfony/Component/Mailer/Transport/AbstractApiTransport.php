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
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractApiTransport extends AbstractHttpTransport
{
    abstract protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface;

    protected function doSendHttp(SentMessage $message): ResponseInterface
    {
        $originalMessage = $message->getOriginalMessage();
        if (!$originalMessage instanceof Message) {
            throw new RuntimeException(\sprintf('Unable to send message with the "%s" transport: the message must be an instance of "%s" (got "%s"). An API transport builds its payload from the message fields; use an SMTP transport to send a raw message.', static::class, Message::class, get_debug_type($originalMessage)));
        }

        try {
            $email = MessageConverter::toEmail($originalMessage);
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('Unable to send message with the "%s" transport: ', static::class).$e->getMessage(), 0, $e);
        }

        return $this->doSendApi($message, $email, $message->getEnvelope());
    }

    /**
     * @return Address[]
     */
    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        return array_filter($envelope->getRecipients(), static fn (Address $address) => !\in_array($address, array_merge($email->getCc(), $email->getBcc()), true));
    }
}
