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
 * @see https://core.telegram.org/bots/api#keyboardbutton
 */
final class KeyboardButton extends AbstractKeyboardButton
{
    public function __construct(string $text)
    {
        $this->options['text'] = $text;
    }

    /**
     * @return $this
     */
    public function requestContact(bool $bool): static
    {
        $this->options['request_contact'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function requestLocation(bool $bool): static
    {
        $this->options['request_location'] = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function requestPollType(string $type): static
    {
        $this->options['request_contact']['type'] = $type;

        return $this;
    }
}
