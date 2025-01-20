<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sweego;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class SweegoTransport extends AbstractTransport
{
    protected const HOST = 'api.sweego.io';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $region,
        private readonly string $campaignType,
        private readonly ?bool $bat,
        private readonly ?string $campaignId,
        private readonly ?bool $shortenUrls,
        private readonly ?bool $shortenWithProtocol,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('sweego://%s%s', $this->getEndpoint(), '?'.http_build_query([
            SweegoOptions::REGION => $this->region,
            SweegoOptions::CAMPAIGN_TYPE => $this->campaignType,
            SweegoOptions::BAT => $this->bat,
            SweegoOptions::CAMPAIGN_ID => $this->campaignId,
            SweegoOptions::SHORTEN_URLS => $this->shortenUrls,
            SweegoOptions::SHORTEN_WITH_PROTOCOL => $this->shortenWithProtocol,
        ]));
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage
            && (null === $message->getOptions() || $message->getOptions() instanceof SweegoOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];

        $body = [
            'recipients' => [
                [
                    'num' => $message->getPhone(),
                    'region' => $options[SweegoOptions::REGION] ?? $this->region,
                ],
            ],
            'message-txt' => $message->getSubject(),
            'channel' => 'sms',
            'provider' => 'sweego',
        ];

        $body = $this->setBat($body, $options);
        $body = $this->setCampaignType($body, $options);
        $body = $this->setCampaignId($body, $options);
        $body = $this->setShortenUrls($body, $options);
        $body = $this->setShortenWithProtocol($body, $options);

        $endpoint = \sprintf('https://%s/send', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Api-Key' => $this->apiKey,
            ],
            'json' => array_filter($body),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Sweego server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException('Unable to send the SMS.', $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId(array_values($success['swg_uids'])[0]);

        return $sentMessage;
    }

    private function setBat(array $body, array $options): array
    {
        $body['bat'] = (bool) ($options[SweegoOptions::BAT] ?? $this->bat);

        return $body;
    }

    private function setCampaignType(array $body, array $options): array
    {
        $body['campaign-type'] = $this->campaignType;

        if (\array_key_exists(SweegoOptions::CAMPAIGN_TYPE, $options) && \is_string($options[SweegoOptions::CAMPAIGN_TYPE])) {
            $body['campaign-type'] = $options[SweegoOptions::CAMPAIGN_TYPE];
        }

        return $body;
    }

    private function setCampaignId(array $body, array $options): array
    {
        $body['campaign-id'] = $this->campaignId;

        if (\array_key_exists(SweegoOptions::CAMPAIGN_ID, $options) && \is_string($options[SweegoOptions::CAMPAIGN_ID])) {
            $body['campaign-id'] = $options[SweegoOptions::CAMPAIGN_ID];
        }

        return $body;
    }

    private function setShortenUrls(array $body, array $options): array
    {
        $body['shorten_urls'] = (bool) ($options[SweegoOptions::SHORTEN_URLS] ?? $this->shortenUrls);

        return $body;
    }

    private function setShortenWithProtocol(array $body, array $options): array
    {
        $body['shorten_with_protocol'] = (bool) ($options[SweegoOptions::SHORTEN_WITH_PROTOCOL] ?? $this->shortenWithProtocol);

        return $body;
    }
}
