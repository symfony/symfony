<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FacebookPage;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Exception\UnsupportedOptionsException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Posts to a Facebook Page feed via the Graph API.
 *
 * Personal-profile publishing is not supported (Meta removed publish_actions in 2018).
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class FacebookPageTransport extends AbstractTransport
{
    protected const HOST = 'graph.facebook.com';

    public function __construct(
        #[\SensitiveParameter] private string $pageAccessToken,
        private string $pageId,
        private string $apiVersion,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('facebook-page://%s?page_id=%s&api_version=%s', $this->getEndpoint(), $this->pageId, $this->apiVersion);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage
            && (null === $message->getOptions() || $message->getOptions() instanceof FacebookPageOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if (($options = $message->getOptions()) && !$options instanceof FacebookPageOptions) {
            throw new UnsupportedOptionsException(__CLASS__, FacebookPageOptions::class, $options);
        }

        $endpoint = \sprintf('%s://%s/%s/%s/feed', $this->getHttpScheme(), $this->getEndpoint(), $this->apiVersion, $this->pageId);

        $body = ['message' => $message->getSubject()] + ($options?->toArray() ?? []);

        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->pageAccessToken,
            'body' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Facebook Graph API server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            try {
                $errorMessage = $response->toArray(false)['error']['message'] ?? null;
            } catch (DecodingExceptionInterface) {
                $errorMessage = $response->getContent(false);
            }

            throw new TransportException(\sprintf('Unable to post the Facebook Page message: error %d ("%s").', $statusCode, $errorMessage ?: 'unknown error'), $response);
        }

        try {
            $content = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException('Unable to post the Facebook Page message: malformed response from the Facebook Graph API.', $response, 0, $e);
        }

        $postId = isset($content['id']) && \is_string($content['id']) ? $content['id'] : '';
        if ('' === $postId) {
            throw new TransportException('Unable to post the Facebook Page message: missing post id.', $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($postId);

        return $sentMessage;
    }
}
