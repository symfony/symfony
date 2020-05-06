<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OvhCloud;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Thomas Ferney <thomas.ferney@gmail.com>
 *
 * @experimental in 5.1
 */
final class OvhCloudTransport extends AbstractTransport
{
    protected const HOST = 'eu.api.ovh.com';

    private $applicationKey;
    private $applicationSecret;
    private $consumerKey;
    private $serviceName;

    public function __construct(string $applicationKey, string $applicationSecret, string $consumerKey, string $serviceName, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->applicationKey = $applicationKey;
        $this->applicationSecret = $applicationSecret;
        $this->consumerKey = $consumerKey;
        $this->serviceName = $serviceName;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('ovhcloud://%s?consumer_key=%s&service_name=%s', $this->getEndpoint(), $this->consumerKey, $this->serviceName);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): void
    {
        if (!$message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, get_debug_type($message)));
        }

        $endpoint = sprintf('https://%s/1.0/sms/%s/jobs', $this->getEndpoint(), $this->serviceName);

        $content = [
            'charset' => 'UTF-8',
            'class' => 'flash',
            'coding' => '8bit',
            'message' => $message->getSubject(),
            'receivers' => [$message->getPhone()],
            'noStopClause' => false,
            'priority' => 'medium',
            'senderForResponse' => true,
        ];

        $now = time() + $this->calculateTimeDelta();
        $headers['X-Ovh-Application'] = $this->applicationKey;
        $headers['X-Ovh-Timestamp'] = $now;

        $toSign = $this->applicationSecret.'+'.$this->consumerKey.'+POST+'.$endpoint.'+'.json_encode($content, JSON_UNESCAPED_SLASHES).'+'.$now;
        $headers['X-Ovh-Consumer'] = $this->consumerKey;
        $headers['X-Ovh-Signature'] = '$1$'.sha1($toSign);

        $response = $this->client->request('POST', $endpoint, [
            'headers' => $headers,
            'json' => $content,
        ]);

        if (200 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException(sprintf('Unable to send the SMS: %s.', $error['message']), $response);
        }
    }

    /**
     * Calculates the time delta between the local machine and the API server.
     */
    private function calculateTimeDelta(): int
    {
        $endpoint = sprintf('%s/auth/time', $this->getEndpoint());
        $response = $this->client->request('GET', $endpoint);

        return $response->getContent() - time();
    }
}
