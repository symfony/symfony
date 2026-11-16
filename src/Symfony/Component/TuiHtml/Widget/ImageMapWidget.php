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
 * An image whose areas are links, like an <img usemap>: it takes the focus, Tab and Shift+Tab (or Right and Left)
 * move between its areas, the current one reversed with the cursor on it, and Enter follows it with a LinkEvent.
 *
 * @experimental
 */
class ImageMapWidget extends ImageWidget implements FocusableInterface, ActivatableInterface, LinksInterface
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
        $count = \count($this->getHrefs());
        // The first area when the focus comes, or the one it was on
        $this->setCurrentArea($focused && $count > 0 ? min($this->currentArea ?? 0, $count - 1) : null);

        return $this;
    }

    /**
     * The href of each area that is a link, in document order.
     *
     * @return list<string>
     */
    public function getHrefs(): array
    {
        return array_values(array_map(static fn (array $area) => $area[3] ?? '', $this->areas));
    }

    public function getCurrentHref(): ?string
    {
        return null !== $this->currentArea ? $this->getHrefs()[$this->currentArea] ?? null : null;
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }
        $keybindings = $this->getKeybindings();
        $count = \count($this->getHrefs());
        $current = $this->currentArea ?? 0;
        if ($keybindings->matches($data, 'next_link') && $current < $count - 1) {
            $this->setCurrentArea($current + 1);
        } elseif ($keybindings->matches($data, 'previous_link') && $current > 0) {
            $this->setCurrentArea($current - 1);
        } elseif ($keybindings->matches($data, 'follow')) {
            $this->activate();
        }
    }

    /**
     * Follows the current area, or the first one.
     */
    public function activate(): bool
    {
        $href = $this->getCurrentHref() ?? $this->getHrefs()[0] ?? null;
        if (null === $href || '' === $href || $this->hasState('disabled')) {
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
        return ['next_link' => 'Next area', 'previous_link' => 'Previous area', 'follow' => 'Follow the area'];
    }

    private function setCurrentArea(?int $index): void
    {
        if ($index !== $this->currentArea) {
            $this->currentArea = $index;
            $this->rendered = [];
            $this->invalidate();
        }
    }
}
