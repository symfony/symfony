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
     */
    public function deliveryAt(?\DateTimeInterface $deliveryAt): self
    {
        $this->options['delivery_at'] = $deliveryAt ? $deliveryAt->getTimestamp() : 0;

        return $this;
    }

    /**
     * The language of the voice message.
     * If set 'auto', the automatic language detection by message text will be used.
     */
    public function voiceLanguage(VoiceLanguage $language): self
    {
        if (VoiceLanguage::Auto === $language) {
            unset($this->options['voice_lang']);
        } else {
            $this->options['voice_lang'] = $language->value;
        }

        return $this;
    }

    /**
     * If True deletes the message from the LOX24 database after delivery.
     */
    public function deleteTextAfterSending(bool $deleteText): self
    {
        $this->options['delete_text'] = $deleteText;

        return $this;
    }

    public function type(Type $type): self
    {
        $this->options['type'] = $type->value;

        return $this;
    }

    /**
     * String which will be sent back to your endpoint. It can be usable to pass your system message id.
     */
    public function callbackData(?string $data): self
    {
        $this->options['callback_data'] = $data;

        return $this;
    }
}
