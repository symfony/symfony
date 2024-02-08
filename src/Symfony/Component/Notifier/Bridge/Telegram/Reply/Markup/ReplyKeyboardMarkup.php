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

use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\KeyboardButton;

/**
 * @author Mihail Krasilnikov <mihail.krasilnikov.j@gmail.com>
 *
 * @see https://core.telegram.org/bots/api#replykeyboardmarkup
 */
final class ReplyKeyboardMarkup extends AbstractTelegramReplyMarkup
{
    public function __construct()
    {
        $this->options['keyboard'] = [];
    }

    /**
     * @param KeyboardButton[] $buttons
     *
     * @return $this
     */
    public function keyboard(array $buttons): static
    {
        $buttons = array_map(static fn (KeyboardButton $button) => $button->toArray(), $buttons);

        $this->options['keyboard'][] = $buttons;

        return $this;
    }

    /**
     * @return $this
     */
    public function resizeKeyboard(bool $bool): static
    {
        $this->options['resize_keyboard'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function oneTimeKeyboard(bool $bool): static
    {
        $this->options['one_time_keyboard'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function selective(bool $bool): static
    {
        $this->options['selective'] = $bool;

        return $this;
    }
}
