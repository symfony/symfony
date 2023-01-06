<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OneSignal;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
final class OneSignalTransport extends AbstractTransport
{
    protected const HOST = 'onesignal.com';

    private $appId;
    private $apiKey;
    private $defaultRecipientId;

    public function __construct(string $appId, #[\SensitiveParameter] string $apiKey, string $defaultRecipientId = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->appId = $appId;
        $this->apiKey = $apiKey;
        $this->defaultRecipientId = $defaultRecipientId;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null === $this->defaultRecipientId) {
            return sprintf('onesignal://%s@%s', urlencode($this->appId), $this->getEndpoint());
        }

        return sprintf('onesignal://%s@%s?recipientId=%s', urlencode($this->appId), $this->getEndpoint(), $this->defaultRecipientId);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage && (null !== $this->defaultRecipientId || ($message->getOptions() instanceof OneSignalOptions && null !== $message->getOptions()->getRecipientId()));
    }

    /**
     * @see https://documentation.onesignal.com/reference/create-notification
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof PushMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, PushMessage::class, $message);
        }

        if ($message->getOptions() && !$message->getOptions() instanceof OneSignalOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, OneSignalOptions::class));
        }

        if (!($opts = $message->getOptions()) && $notification = $message->getNotification()) {
            $opts = OneSignalOptions::fromNotification($notification);
        }

        $recipientId = $message->getRecipientId() ?? $this->defaultRecipientId;

        if (null === $recipientId) {
            throw new LogicException(sprintf('The "%s" transport should have configured `defaultRecipientId` via DSN or provided with message options.', __CLASS__));
        }

        $options = $opts ? $opts->toArray() : [];
        $options['app_id'] = $this->appId;
        $options['include_player_ids'] = [$recipientId];

        if (!isset($options['headings'])) {
            $options['headings'] = ['en' => $message->getSubject()];
        }
        if (!isset($options['contents'])) {
            $options['contents'] = ['en' => $message->getContent()];
        }

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/api/v1/notifications', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Basic '.$this->apiKey,
            ],
            'json' => $options,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote OneSignal server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException(sprintf('Unable to send the OneSignal push notification: "%s".', $response->getContent(false)), $response);
        }

        $result = $response->toArray(false);

        if (empty($result['id'])) {
            throw new TransportException(sprintf('Unable to send the OneSignal push notification: "%s".', $response->getContent(false)), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['id']);

        return $sentMessage;
    }
}
