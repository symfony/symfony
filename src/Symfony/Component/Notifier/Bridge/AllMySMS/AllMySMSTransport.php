<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AllMySMS;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Quentin Dequippe <quentin@dequippe.tech>
 */
final class AllMySMSTransport extends AbstractTransport
{
    protected const HOST = 'api.allmysms.com';

    private $login;
    private $apiKey;
    private $tpoa;

    public function __construct(string $login, string $apiKey, string $tpoa = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->login = $login;
        $this->apiKey = $apiKey;
        $this->tpoa = $tpoa;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('allmysms://%s?from=%s', $this->getEndpoint(), $this->tpoa);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): void
    {
        if (!$message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, \get_class($message)));
        }

        $endpoint = sprintf('https://%s/http/9.0/', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'body' => [
                'login' => $this->login,
                'apiKey' => $this->apiKey,
                'tpoa' => $this->tpoa,
                'mobile' => $message->getPhone(),
                'message' => $message->getSubject(),
            ],
        ]);

        $result = $response->toArray();

        if (100 != $result['status']) {
            throw new TransportException(sprintf('Unable to send the SMS: %s (%s).', $result['statusText'], $result['status']), $response);
        }
    }
}
