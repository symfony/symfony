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
}
