<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SpotHit;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author James Hemery <james@yieldstudio.fr>
 */
final class SpotHitTransport extends AbstractTransport
{
    protected const HOST = 'spot-hit.fr';

    private string $token;
    private ?string $from;

    public function __construct(#[\SensitiveParameter] string $token, string $from = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (!$this->from) {
            return sprintf('spothit://%s', $this->getEndpoint());
        }

        return sprintf('spothit://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    /**
     * @param MessageInterface|SmsMessage $message
     *
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$this->supports($message)) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $from = $message->getFrom() ?: $this->from;

        $endpoint = sprintf('https://www.%s/api/envoyer/sms', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'body' => [
                'key' => $this->token,
                'destinataires' => $message->getPhone(),
                'type' => 'premium',
                'message' => $message->getSubject(),
                'expediteur' => $from,
            ],
        ]);

        try {
            $data = $response->toArray();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote SpotHit server.', $response, 0, $e);
        } catch (HttpExceptionInterface|DecodingExceptionInterface $e) {
            throw new TransportException('Unexpected reply from the remote SpotHit server.', $response, 0, $e);
        }

        if (!$data['resultat']) {
            $errors = \is_array($data['erreurs']) ? implode(',', $data['erreurs']) : $data['erreurs'];
            throw new TransportException(sprintf('[HTTP %d] Unable to send the SMS: error(s) "%s".', $response->getStatusCode(), $errors), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($data['id']);

        return $sentMessage;
    }
}
