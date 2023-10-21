<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup;

use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\InlineKeyboardButton;

/**
 * @author Mihail Krasilnikov <mihail.krasilnikov.j@gmail.com>
 *
 * @see https://core.telegram.org/bots/api#inlinekeyboardmarkup
 */
final class InlineKeyboardMarkup extends AbstractTelegramReplyMarkup
{
    public function __construct()
    {
        $this->options['inline_keyboard'] = [];
    }

    /**
     * @param InlineKeyboardButton[] $buttons
     *
     * @return $this
     */
    public function inlineKeyboard(array $buttons): static
    {
        $buttons = array_map(static fn (InlineKeyboardButton $button) => $button->toArray(), $buttons);

        $this->options['inline_keyboard'][] = $buttons;

        return $this;
    }
}
