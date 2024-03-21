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

use DateTimeInterface;
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
     * DateTime object of SMS the delivery time.
     * If Null or not set, the message will be sent immediately.
     * E.g. deliveryAt(new DateTime('2024-03-21 12:17:00')) or deliveryAt(null)
     */
    public function deliveryAt(?DateTimeInterface $deliveryAt = null): self
    {
        $this->options['delivery_at'] = $deliveryAt ? $deliveryAt->getTimestamp() : 0;

        return $this;
    }


    /**
     * The language of the voice message.
     * If not set or 'auto', the automatic language detection by message text will be used.
     * E.g. voiceLanguage('en') or voiceLanguage('auto')
     */
    public function voiceLanguage(?string $language): self
    {
        if (!$language) {
            unset($this->options['voice_lang']);

            return $this;
        }

        $language = strtolower($language);

        if (!VoiceLanguage::tryFrom($language)) {
            $allowed = implode(', ', array_map(static fn ($case) => $case->value, VoiceLanguage::cases()));
            throw new InvalidArgumentException(
                sprintf("The language '%s' is not supported; supported languages are: %s.", $language, $allowed)
            );
        }

        $this->options['voice_lang'] = $language;

        return $this;
    }

    /**
     * If True deletes the message from the LOX24 database after delivery
     * E.g. textDelete(true) or textDelete(false)
     */
    public function textDelete(bool $isTextDelete): self
    {
        $this->options['is_text_delete'] = $isTextDelete;

        return $this;
    }

    /**
     * The message type: 'sms' or 'voice'.
     * E.g. type('sms') or type('voice')
     */
    public function type(string $type): self
    {
        $service = Type::tryFrom($type);
        if (!$service) {
            throw new InvalidArgumentException(sprintf("Invalid type: %s", $type));
        }

        $this->options['type'] = $service->value;

        return $this;
    }

    /**
     * String which will be sent back to your endpoint. It can be usable to pass your system message id.
     * E.g. callbackData('internal_message_id_123')
     */
    public function callbackData(?string $data): self
    {
        $this->options['callback_data'] = $data;

        return $this;
    }
}
