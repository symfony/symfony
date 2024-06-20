<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Lox24;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
final class Lox24Transport extends AbstractTransport
{
    protected const HOST = 'api.lox24.eu';

    public function __construct(
        private readonly string $user,
        #[\SensitiveParameter] private readonly string $token,
        private readonly string $from,
        private readonly array $options = [],
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $params = [
            'from' => $this->from,
            ...$this->options,
        ];

        $query = $params ? '?'.http_build_query($params) : '';

        return \sprintf('lox24://%s%s', $this->getEndpoint(), $query);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage
            && (null === $message->getOptions() || $message->getOptions() instanceof Lox24Options);
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$this->supports($message)) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $from = $message->getFrom() ?: $this->from;

        if (!$this->isFromValid($from)) {
            throw new InvalidArgumentException(\sprintf('The "From" number "%s" is not a valid phone number, shortcode, or alphanumeric sender ID.', $from));
        }

        $body = [
            'sender_id' => $from,
            'phone' => $message->getPhone(),
            'text' => $message->getSubject(),
        ];

        $options = $message->getOptions()?->toArray() ?? [];
        $body = $this->setIsTextDeleted($body, $options);
        $body = $this->setCallbackData($body, $options);
        $body = $this->setDeliveryAt($body, $options);
        $body = $this->setServiceCode($body, $options);
        $body = $this->setVoiceLang($body, $options);

        $response = $this->client->request('POST', \sprintf('https://%s/sms', $this->getEndpoint()), [
            'headers' => [
                'X-LOX24-AUTH-TOKEN' => \sprintf('%s:%s', $this->user, $this->token),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'LOX24 Symfony Notifier',
            ],
            'json' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote LOX24 server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException(\sprintf('Unable to send the SMS: "%s".', $error['detail']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['uuid']);

        return $sentMessage;
    }

    private function isFromValid(string $from): bool
    {
        return preg_match('/^[.\-a-zA-Z0-9_ ]{2,11}$/', $from) || preg_match('/^\+[1-9]\d{1,14}$/', $from);
    }

    private function setIsTextDeleted(array $body, array $options): array
    {
        $body['is_text_deleted'] = (bool) ($options['delete_text'] ?? false);

        return $body;
    }

    private function setCallbackData(array $body, array $options): array
    {
        if (!empty($options['callback_data'])) {
            $body['callback_data'] = $options['callback_data'];
        }

        return $body;
    }

    private function setDeliveryAt(array $body, array $options): array
    {
        $body['delivery_at'] = max((int) ($options['delivery_at'] ?? 0), 0);

        return $body;
    }

    private function setServiceCode(array $body, array $options): array
    {
        $code = $options['type'] ?? Type::Sms->value;

        try {
            $type = Type::from((string) $code);
        } catch (\ValueError) {
            throw new InvalidArgumentException(\sprintf('Invalid type: "%s".', $code));
        }

        $body['service_code'] = $type->getServiceCode();

        return $body;
    }

    private function setVoiceLang(array $body, array $options): array
    {
        $voiceLang = $options['voice_lang'] ?? null;
        if ($voiceLang) {
            $voiceLang = strtoupper($voiceLang);
            try {
                $lang = VoiceLanguage::from($voiceLang);
            } catch (\ValueError) {
                $allowed = implode(', ', array_map(static fn ($case) => $case->value, VoiceLanguage::cases()));
                $str = 'The "voice_lang" option "%s" is not a valid language. Allowed languages are: %s.';
                throw new InvalidArgumentException(\sprintf($str, $voiceLang, $allowed));
            }

            if (VoiceLanguage::Auto !== $lang) {
                $body['voice_lang'] = $lang->value;
            }
        }

        return $body;
    }
}
