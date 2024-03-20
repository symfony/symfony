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

use InvalidArgumentException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
final class Lox24Options implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }


    /**
     * Unix timestamp of SMS the delivery time. If 0 or not set, the message will be sent immediately.
     */
    public function deliveryAt(int $deliveryAt): self
    {
        $this->options['delivery_at'] = max($deliveryAt, 0);

        return $this;
    }


    /**
     * The language of the voice message.
     * If not set, the automatic language detection by message text will be used.
     */
    public function voiceLanguage(?string $language): self
    {
        if($language) {
            $language = strtolower($language);
        }

        if ($language && !in_array($language, Lox24Transport::ALLOWED_VOICE_LANGUAGES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The language "%s" is not supported; supported languages are: %s.',
                    $language,
                    implode(', ', Lox24Transport::ALLOWED_VOICE_LANGUAGES)
                )
            );
        }

        $this->options['voice_lang'] = $language;

        return $this;
    }

    /**
     * If true delete from DB the message text after sending.
     */
    public function textDelete(bool $isTextDelete): self
    {
        $this->options['is_text_delete'] = $isTextDelete;

        return $this;
    }

    /**
     * The message type: 'sms' or 'voice'.
     */
    public function type(string $type): self
    {
        $service = Type::tryFrom($type);
        if (!$service) {
            throw new InvalidArgumentException("Invalid type: $type");
        }

        $this->options['type'] = $service->value;

        return $this;
    }

    /**
     * String which will be sent back to your endpoint.
     * E.g. it can be usable to pass your system message id.
     */
    public function callbackData(?string $data): self
    {
        $this->options['callback_data'] = $data;

        return $this;
    }


}