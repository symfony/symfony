<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoogleChat;

use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class GoogleChatOptions implements MessageOptionsInterface
{
    private ?string $threadKey = null;
    private array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public static function fromNotification(Notification $notification): self
    {
        $options = new self();

        $text = $notification->getEmoji().' *'.$notification->getSubject().'* ';

        if ($notification->getContent()) {
            $text .= "\r\n".$notification->getContent();
        }

        if ($exception = $notification->getExceptionAsString()) {
            $text .= "\r\n".'```'.$exception.'```';
        }

        $options->text($text);

        return $options;
    }

    public static function fromMessage(ChatMessage $message): self
    {
        $options = new self();

        $options->text($message->getSubject());

        return $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    /**
     * @deprecated since Symfony 6.3, use "cardV2()" instead
     *
     * @return $this
     */
    public function card(array $card): static
    {
        trigger_deprecation('symfony/google-chat-notifier', '6.3', '"%s()" is deprecated, use "cardV2()" instead.', __METHOD__);

        $this->options['cards'][] = $card;

        return $this;
    }

    /**
     * @return $this
     */
    public function cardV2(array $card): static
    {
        $this->options['cardsV2'][] = $card;

        return $this;
    }

    /**
     * @return $this
     */
    public function text(string $text): static
    {
        $this->options['text'] = $text;

        return $this;
    }

    /**
     * @return $this
     */
    public function setThreadKey(?string $threadKey): static
    {
        $this->threadKey = $threadKey;

        return $this;
    }

    public function getThreadKey(): ?string
    {
        return $this->threadKey;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
