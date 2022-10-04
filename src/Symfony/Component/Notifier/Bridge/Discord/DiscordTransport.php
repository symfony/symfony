<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord;

use Symfony\Component\Notifier\Exception\LengthException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Piot <math.piot@gmail.com>
 */
final class DiscordTransport extends AbstractTransport
{
    protected const HOST = 'discord.com';

    private const SUBJECT_LIMIT = 2000;

    private string $token;
    private string $webhookId;

    public function __construct(#[\SensitiveParameter] string $token, string $webhookId, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        $this->webhookId = $webhookId;
        $this->client = $client;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('discord://%s?webhook_id=%s', $this->getEndpoint(), $this->webhookId);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    /**
     * @see https://discord.com/developers/docs/resources/webhook
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $messageOptions = $message->getOptions();
        $options = $messageOptions ? $messageOptions->toArray() : [];

        $content = $message->getSubject();

        if (mb_strlen($content, 'UTF-8') > self::SUBJECT_LIMIT) {
            throw new LengthException(sprintf('The subject length of a Discord message must not exceed %d characters.', self::SUBJECT_LIMIT));
        }

        $endpoint = sprintf('https://%s/api/webhooks/%s/%s', $this->getEndpoint(), $this->webhookId, $this->token);
        $options['content'] = $content;
        $response = $this->client->request('POST', $endpoint, [
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Discord server.', $response, 0, $e);
        }

        if (204 !== $statusCode) {
            $result = $response->toArray(false);

            if (401 === $statusCode) {
                $originalContent = $message->getSubject();
                $errorMessage = $result['message'];
                $errorCode = $result['code'];
                throw new TransportException(sprintf('Unable to post the Discord message: "%s" (%d: "%s").', $originalContent, $errorCode, $errorMessage), $response);
            }

            if (400 === $statusCode) {
                $originalContent = $message->getSubject();

                $errorMessage = '';
                foreach ($result as $fieldName => $message) {
                    $message = \is_array($message) ? implode(' ', $message) : $message;
                    $errorMessage .= $fieldName.': '.$message.' ';
                }

                $errorMessage = trim($errorMessage);
                throw new TransportException(sprintf('Unable to post the Discord message: "%s" (%s).', $originalContent, $errorMessage), $response);
            }

            throw new TransportException(sprintf('Unable to post the Discord message: "%s" (Status Code: %d).', $message->getSubject(), $statusCode), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
