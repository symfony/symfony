<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

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

    public const ALLOWED_VOICE_LANGUAGES = ['en', 'de', 'es', 'fr', 'it', 'auto'];

    public function __construct(
        #[\SensitiveParameter] private readonly string $auth,
        private readonly string                        $from,
        private readonly array                         $options = [],
        ?HttpClientInterface                           $client = null,
        ?EventDispatcherInterface                      $dispatcher = null
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

        return "lox24://{$this->getEndpoint()}$query";
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
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $from = $message->getFrom() ?: $this->from;

        if (!$this->isFromValid($from)) {
            throw new InvalidArgumentException(
                "The \"From\" number \"$from\" is not a valid phone number, shortcode, or alphanumeric sender ID."
            );
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

        $response = $this->client->request('POST', "https://{$this->getEndpoint()}/sms", [
            'headers' => [
                'X-LOX24-AUTH-TOKEN' => $this->auth,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'LOX24 Symfony Notifier',
            ],
            'body' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote LOX24 server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException(
                "Unable to send the SMS: {$error['detail']}", $response
            );
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string)$this);
        $sentMessage->setMessageId($success['uuid']);

        return $sentMessage;
    }


    private function setVoiceLang(array $body, array $options): array
    {
        $voiceLang = $options['voice_lang'] ?? null;
        if ($voiceLang) {
            $voiceLang = strtolower($voiceLang);
            if (!$this->isVoiceLangValid($voiceLang)) {
                throw new InvalidArgumentException(
                    "The \"voice_lang\" option \"$voiceLang\" is not a valid language. Allowed languages are: "
                    .implode(', ', self::ALLOWED_VOICE_LANGUAGES)."."
                );
            }

            if($voiceLang !== 'auto') {
                $body['voice_lang'] = $voiceLang;
            }
        }

        return $body;
    }

    private function isFromValid(string $from): bool
    {
        return preg_match('/^[.\-a-zA-Z0-9_ ]{2,11}$/', $from) || preg_match('/^\+[1-9]\d{1,14}$/', $from);
    }

    private function isVoiceLangValid(string $voiceLang): bool
    {
        return in_array($voiceLang, self::ALLOWED_VOICE_LANGUAGES, true);
    }

    private function setServiceCode(array $body, array $options): array
    {
        $code = $options['type'] ?? Type::Sms->value;
        $type = Type::tryFrom((string)$code);

        if (!$type) {
            throw new InvalidArgumentException("Invalid type: $code");
        }

        $body['service_code'] = $type === Type::Voice ? 'text2speech' : 'direct';

        return $body;
    }

    private function setDeliveryAt(array $body, array $options): array
    {
        $body['delivery_at'] = max((int)($options['delivery_at'] ?? 0), 0);

        return $body;
    }


    private function setIsTextDeleted(array $body, array $options): array
    {
        $body['is_text_deleted'] = (bool)($options['is_text_delete'] ?? false);

        return $body;
    }

    private function setCallbackData(array $body, array $options): array
    {
        if (!empty($options['callback_data'])) {
            $body['callback_data'] = $options['callback_data'];
        }

        return $body;
    }

}