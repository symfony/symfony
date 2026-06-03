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

use Symfony\Component\Notifier\Exception\LogicException;
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
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
final class DiscordBotTransport extends AbstractTransport
{
    protected const HOST = 'discord.com';

    public function __construct(
        #[\SensitiveParameter] private string $token,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('discord+bot://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && $message->getOptions() instanceof DiscordOptions;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $channelId = $message->getOptions()?->getRecipientId();
        if (null === $channelId) {
            throw new LogicException('Missing configured recipient id on Discord message.');
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['content'] = $message->getSubject();

        $endpoint = \sprintf('https://%s/api/channels/%s/messages', $this->getEndpoint(), $channelId);
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => 'Bot '.$this->token,
            ],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Discord server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $result = $response->toArray(false);

            if (401 === $statusCode) {
                $originalContent = $message->getSubject();
                $errorMessage = $result['message'];
                $errorCode = $result['code'];
                throw new TransportException(\sprintf('Unable to post the Discord message: "%s" (%d: "%s").', $originalContent, $errorCode, $errorMessage), $response);
            }

            if (400 === $statusCode) {
                $originalContent = $message->getSubject();

                $errorMessage = '';
                foreach ($result as $fieldName => $message) {
                    $message = \is_array($message) ? implode(' ', $message) : $message;
                    $errorMessage .= $fieldName.': '.$message.' ';
                }

                $errorMessage = trim($errorMessage);
                throw new TransportException(\sprintf('Unable to post the Discord message: "%s" (%s).', $originalContent, $errorMessage), $response);
            }

            throw new TransportException(\sprintf('Unable to post the Discord message: "%s" (Status Code: %d).', $message->getSubject(), $statusCode), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
