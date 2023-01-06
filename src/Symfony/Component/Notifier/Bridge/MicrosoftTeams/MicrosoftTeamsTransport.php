<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams;

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
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MicrosoftTeamsTransport extends AbstractTransport
{
    protected const ENDPOINT = 'outlook.office.com';

    private string $path;

    public function __construct(string $path, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->path = $path;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('microsoftteams://%s%s', $this->getEndpoint(), $this->path);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    /**
     * @see https://docs.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/connectors-using#post-a-message-to-the-webhook-using-curl
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if ($message->getOptions() && !$message->getOptions() instanceof MicrosoftTeamsOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, MicrosoftTeamsOptions::class));
        }

        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];

        $options['text'] ??= $message->getSubject();

        $path = $message->getRecipientId() ?? $this->path;
        $endpoint = sprintf('https://%s%s', $this->getEndpoint(), $path);
        $response = $this->client->request('POST', $endpoint, [
            'json' => $options,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote MicrosoftTeams server.', $response, 0, $e);
        }

        $requestId = $response->getHeaders(false)['request-id'][0] ?? null;
        if (null === $requestId) {
            $originalContent = $message->getSubject();

            throw new TransportException(sprintf('Unable to post the Microsoft Teams message: "%s" (request-id not found).', $originalContent), $response);
        }

        if (200 !== $statusCode) {
            $errorMessage = $response->getContent(false);
            $originalContent = $message->getSubject();

            throw new TransportException(sprintf('Unable to post the Microsoft Teams message: "%s" (%s : "%s").', $originalContent, $requestId, $errorMessage), $response);
        }

        $message = new SentMessage($message, (string) $this);
        $message->setMessageId($requestId);

        return $message;
    }
}
