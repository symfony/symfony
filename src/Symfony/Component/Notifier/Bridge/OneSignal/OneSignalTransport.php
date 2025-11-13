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

    public function __construct(
        private string $appId,
        #[\SensitiveParameter] private string $apiKey,
        private ?string $defaultRecipientId = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null === $this->defaultRecipientId) {
            return \sprintf('onesignal://%s@%s', urlencode($this->appId), $this->getEndpoint());
        }

        return \sprintf('onesignal://%s@%s?recipientId=%s', urlencode($this->appId), $this->getEndpoint(), $this->defaultRecipientId);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage && (null === $message->getOptions() || $message->getOptions() instanceof OneSignalOptions);
    }

    /**
     * @see https://documentation.onesignal.com/reference/create-notification
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof PushMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, PushMessage::class, $message);
        }

        if (!($options = $message->getOptions()) && $notification = $message->getNotification()) {
            $options = OneSignalOptions::fromNotification($notification);
        }

        $recipientId = $message->getRecipientId() ?? $this->defaultRecipientId;

        if (null === $recipientId) {
            throw new LogicException(\sprintf('The "%s" transport should have configured `defaultRecipientId` via DSN or provided with message options.', __CLASS__));
        }

        $options = $options?->toArray() ?? [];
        $options['app_id'] = $this->appId;
        if ($options['is_external_user_id'] ?? false) {
            $options['include_aliases'] = [
                'external_id' => [$recipientId],
            ];
            $options['target_channel'] = 'push';
            unset($options['is_external_user_id']);
        } else {
            $options['include_subscription_ids'] = [$recipientId];
        }
        $options['headings'] ??= ['en' => $message->getSubject()];
        $options['contents'] ??= ['en' => $message->getContent()];

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
            throw new TransportException(\sprintf('Unable to send the OneSignal push notification: "%s".', $response->getContent(false)), $response);
        }

        $result = $response->toArray(false);

        if (empty($result['id'])) {
            throw new TransportException(\sprintf('Unable to send the OneSignal push notification: "%s".', $response->getContent(false)), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['id']);

        return $sentMessage;
    }
}
