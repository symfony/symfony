<?php

declare(strict_types = 1);

namespace Symfony\Component\Notifier\Bridge\Iterable;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Balázs Csaba <csaba.balazs@lingoda.com>
 */
final class IterableTransport extends AbstractTransport
{
    protected const HOST = 'api.iterable.com/api/push/target';
    private const RESPONSE_CODE_SUCCESS = 'Success';

    /** @var string */
    private $apiKey;

    /** @var string|null */
    private $campaignId;

    public function __construct(string $apiKey, ?string $campaignId, ?HttpClientInterface $client = null, ?EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;
        $this->campaignId = $campaignId;
        $this->client = $client;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('iterable://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if ($message->getOptions() && !$message->getOptions() instanceof IterableOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, IterableOptions::class));
        }

        $endpoint = sprintf('https://%s', $this->getEndpoint());
        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];

        $campaignId = $options['campaignId'] ?? $this->campaignId;
        if (null === $campaignId) {
            throw new InvalidArgumentException(sprintf('The "%s" transport required the "campaignId" to be set.', __CLASS__));
        }

        $options['campaignId'] = (int) $campaignId;
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'api_key' => $this->apiKey,
            ],
            'json' => $options,
        ]);

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        $content = 0 === strpos($contentType, 'application/json') ? $response->toArray(false) : null;

        if (200 !== $response->getStatusCode()) {
            $errorMessage = $content ? $content['msg'] : $response->getContent(false);

            throw new TransportException('Unable to post the Iterable message: ' . $errorMessage, $response);
        }
        if ($content && $content['code'] !== self::RESPONSE_CODE_SUCCESS) {
            throw new TransportException('Unable to post the Iterable message: ' . $content['msg'], $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
