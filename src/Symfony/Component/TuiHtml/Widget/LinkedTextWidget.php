<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\TuiHtml\Event\LinkEvent;

/**
 * A text block with links that takes the focus, so that its links are reached and followed with the keyboard.
 *
 * Focused, one of its links is shown as the current one, reversed, the
 * cursor on it: Tab or Right goes to the next one, Shift+Tab or Left to the
 * one before, and Enter follows it, dispatching a {@see LinkEvent}.
 *
 * @experimental
 */
class LinkedTextWidget extends RichTextWidget implements FocusableInterface, ActivatableInterface, LinksInterface
{
    use FocusableTrait {
        setFocused as private setFocusedState;
    }
    use KeybindingsTrait;

    /**
     * @return $this
     */
    public function setFocused(bool $focused): static
    {
        $this->setFocusedState($focused);
        $count = \count($this->getLinks());
        // The first link when the focus comes, or the one it was on
        $this->setCurrentLink($focused && $count > 0 ? min($this->getCurrentLink() ?? 0, $count - 1) : null);

        return $this;
    }

    /**
     * The href of the current link, null when it has not the focus.
     */
    public function getCurrentHref(): ?string
    {
        return null !== ($index = $this->getCurrentLink()) ? $this->getLinks()[$index] ?? null : null;
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }
        $keybindings = $this->getKeybindings();
        $count = \count($this->getLinks());
        $current = $this->getCurrentLink() ?? 0;
        if ($keybindings->matches($data, 'next_link') && $current < $count - 1) {
            $this->setCurrentLink($current + 1);
        } elseif ($keybindings->matches($data, 'previous_link') && $current > 0) {
            $this->setCurrentLink($current - 1);
        } elseif ($keybindings->matches($data, 'follow')) {
            $this->activate();
        }
    }

    /**
     * Follows the current link, or the first one.
     */
    public function activate(): bool
    {
        $href = $this->getCurrentHref() ?? $this->getLinks()[0] ?? null;
        if (null === $href || $this->hasState('disabled')) {
            return false;
        }
        $this->dispatch(new LinkEvent($this, $href));

        return true;
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return ['next_link' => [Key::TAB, Key::RIGHT], 'previous_link' => ['shift+tab', Key::LEFT], 'follow' => [Key::ENTER]];
    }

    /**
     * @return array<string, string>
     */
    protected static function getDefaultKeybindingLabels(): array
    {
        return ['next_link' => 'Next link', 'previous_link' => 'Previous link', 'follow' => 'Follow the link'];
    }
}
