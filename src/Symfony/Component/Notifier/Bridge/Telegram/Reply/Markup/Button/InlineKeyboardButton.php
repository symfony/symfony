<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button;

/**
 * @author Mihail Krasilnikov <mihail.krasilnikov.j@gmail.com>
 *
 * @see https://core.telegram.org/bots/api#inlinekeyboardbutton
 */
final class InlineKeyboardButton extends AbstractKeyboardButton
{
    public function __construct(string $text = '')
    {
        $this->options['text'] = $text;
    }

    /**
     * @return $this
     */
    public function url(string $url): static
    {
        $this->options['url'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function loginUrl(string $url): static
    {
        $this->options['login_url']['url'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function loginUrlForwardText(string $text): static
    {
        $this->options['login_url']['forward_text'] = $text;

        return $this;
    }

    /**
     * @return $this
     */
    public function requestWriteAccess(bool $bool): static
    {
        $this->options['login_url']['request_write_access'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function callbackData(string $data): static
    {
        $this->options['callback_data'] = $data;

        return $this;
    }

    /**
     * @return $this
     */
    public function switchInlineQuery(string $query): static
    {
        $this->options['switch_inline_query'] = $query;

        return $this;
    }

    /**
     * @return $this
     */
    public function payButton(bool $bool): static
    {
        $this->options['pay'] = $bool;

        return $this;
    }
}
