<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram;

use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\AbstractTelegramReplyMarkup;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Mihail Krasilnikov <mihail.krasilnikov.j@gmail.com>
 */
final class TelegramOptions implements MessageOptionsInterface
{
    public const PARSE_MODE_HTML = 'HTML';
    public const PARSE_MODE_MARKDOWN = 'Markdown';
    public const PARSE_MODE_MARKDOWN_V2 = 'MarkdownV2';

    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['chat_id'] ?? null;
    }

    /**
     * @return $this
     */
    public function chatId(string $id): static
    {
        $this->options['chat_id'] = $id;

        return $this;
    }

    /**
     * @return $this
     */
    public function parseMode(string $mode): static
    {
        $this->options['parse_mode'] = $mode;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableWebPagePreview(bool $bool): static
    {
        $this->options['disable_web_page_preview'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableNotification(bool $bool): static
    {
        $this->options['disable_notification'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function protectContent(bool $bool): static
    {
        $this->options['protect_content'] = $bool;

        return $this;
    }

    /**
     * Work only when photo option is defined.
     *
     * @return $this
     */
    public function hasSpoiler(bool $bool): static
    {
        $this->options['has_spoiler'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function photo(string $url): static
    {
        $this->options['photo'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function uploadPhoto(string $path): static
    {
        $this->options['upload']['photo'] = $path;

        return $this;
    }

    /**
     * @return $this
     */
    public function replyTo(int $messageId): static
    {
        $this->options['reply_to_message_id'] = $messageId;

        return $this;
    }

    /**
     * @return $this
     */
    public function replyMarkup(AbstractTelegramReplyMarkup $markup): static
    {
        $this->options['reply_markup'] = $markup->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function edit(int $messageId): static
    {
        $this->options['message_id'] = $messageId;

        return $this;
    }

    /**
     * @return $this
     */
    public function answerCallbackQuery(string $callbackQueryId, bool $showAlert = false, int $cacheTime = 0): static
    {
        $this->options['callback_query_id'] = $callbackQueryId;
        $this->options['show_alert'] = $showAlert;

        if ($cacheTime > 0) {
            $this->options['cache_time'] = $cacheTime;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function location(float $latitude, float $longitude): static
    {
        $this->options['location'] = ['latitude' => $latitude, 'longitude' => $longitude];

        return $this;
    }

    /**
     * @return $this
     */
    public function venue(float $latitude, float $longitude, string $title, string $address): static
    {
        $this->options['venue'] = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
        ];

        return $this;
    }

    /**
     * @return $this
     */
    public function document(string $url): static
    {
        $this->options['document'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function uploadDocument(string $path): static
    {
        $this->options['upload']['document'] = $path;

        return $this;
    }

    /**
     * @return $this
     */
    public function video(string $url): static
    {
        $this->options['video'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function uploadVideo(string $path): static
    {
        $this->options['upload']['video'] = $path;

        return $this;
    }

    /**
     * @return $this
     */
    public function audio(string $url): static
    {
        $this->options['audio'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function uploadAudio(string $path): static
    {
        $this->options['upload']['audio'] = $path;

        return $this;
    }

    /**
     * @return $this
     */
    public function animation(string $url): static
    {
        $this->options['animation'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function uploadAnimation(string $path): static
    {
        $this->options['upload']['animation'] = $path;

        return $this;
    }

    /**
     * @return $this
     */
    public function sticker(string $url, ?string $emoji = null): static
    {
        $this->options['sticker'] = $url;
        $this->options['emoji'] = $emoji;

        return $this;
    }

    /**
     * @return $this
     */
    public function uploadSticker(string $path, ?string $emoji = null): static
    {
        $this->options['upload']['sticker'] = $path;
        $this->options['emoji'] = $emoji;

        return $this;
    }

    /**
     * @return $this
     */
    public function contact(string $phoneNumber, string $firstName, ?string $lastName = null, ?string $vCard = null): static
    {
        $this->options['contact'] = [
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
        ];

        if (null !== $lastName) {
            $this->options['contact']['last_name'] = $lastName;
        }

        if (null !== $vCard) {
            $this->options['contact']['vcard'] = $vCard;
        }

        return $this;
    }
}
