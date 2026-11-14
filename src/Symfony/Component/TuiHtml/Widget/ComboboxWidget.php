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

use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Style\Position;
use Symfony\Component\TuiHtml\Style\PositionMode;
use Symfony\Component\TuiHtml\Style\Style;

/**
 * An input with suggestions to pick from, like an HTML <input list> and its <datalist>.
 *
 * The suggestions containing what is typed are listed below the input, over
 * what follows, while it has the focus; Down (or Alt+Down) opens them, Up and
 * Down move in them, Enter takes one and Escape hides them, the focus staying
 * in the input. An input with keys of its own, like a number field stepping
 * with Up and Down, keeps them while the suggestions are hidden: only Alt+Down
 * opens them, and what those keys change does not.
 *
 * @experimental
 */
class ComboboxWidget extends ContainerWidget implements CompositeControlInterface
{
    /** The most suggestions listed at once */
    private const VISIBLE = 8;
    private readonly SelectListWidget $list;
    private readonly Keybindings $keys;
    private bool $open = false;
    /** Escape hid the suggestions: they come back with the next change */
    private bool $dismissed = false;
    /** The input's own keys are changing it: the suggestions stay as they are */
    private bool $ownKeys = false;
    /** Opened with a key while none contains the value: all the suggestions, like a browser's */
    private bool $all = false;

    /**
     * @param list<string>                  $suggestions
     * @param (\Closure(string): bool)|null $inputKeys   The keys of the input itself while the suggestions are hidden, like the
     *                                                   steps of a number field: true when it used them
     */
    public function __construct(private readonly InputWidget $input, private readonly array $suggestions, private readonly ?\Closure $inputKeys = null)
    {
        $this->keys = new Keybindings(['up' => [Key::UP], 'down' => [Key::DOWN], 'open' => null !== $inputKeys ? ['alt+down'] : [Key::DOWN, 'alt+down'], 'take' => [Key::ENTER], 'hide' => [Key::ESCAPE]]);
        $this->list = new SelectListWidget([], self::VISIBLE);
        $widest = max(1, ...array_map(mb_strwidth(...), $suggestions ?: ['']));
        StyleBridge::set($this->list, new Style(null, null, null, null, null, null, null, null, null, null, null, null, true, null, null, null, $widest + 6, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, new Position(PositionMode::Absolute, null, null, null, 0, 100, ['top' => 100])));
        $this->input->on(ChangeEvent::class, function (): void {
            if ($this->ownKeys) {
                return;
            }
            $this->dismissed = false;
            $this->all = false;
            $this->refresh();
        });
        $this->input->onInput(fn (string $data): bool => $this->handleKey($data));
        StyleBridge::set($this, new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, new Position(PositionMode::Relative)));
        $this->add($this->input);
        $this->add($this->list);
    }

    public function getInput(): InputWidget
    {
        return $this->input;
    }

    public function getControl(): AbstractWidget
    {
        return $this->input;
    }

    public function getList(): SelectListWidget
    {
        return $this->list;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * The suggestions that contain the value, like a browser lists them as it is typed, or all of them, like when the
     * list is opened with a key and none does; not the value itself.
     *
     * @return list<string>
     */
    public function getMatches(?bool $all = null): array
    {
        $value = $this->input->getValue();
        $all ??= $this->all;

        return array_values(array_filter($this->suggestions, static fn (string $suggestion) => $suggestion !== $value && ($all || '' === $value || false !== mb_stripos($suggestion, $value))));
    }

    public function beforeRender(): void
    {
        // The focus went elsewhere: the suggestions go too
        if ($this->open && !$this->input->isFocused() && null !== $this->getContext()) {
            $this->show(false);
        }
        parent::beforeRender();
    }

    /**
     * Keys for the suggestions, before the input gets them: true when they were used.
     */
    private function handleKey(string $data): bool
    {
        if (!$this->open) {
            if ($this->keys->matches($data, 'open') && $this->getMatches(true)) {
                $this->dismissed = false;
                // What contains the value, else all of them
                $this->all = !$this->getMatches(false);
                $this->refresh();

                return true;
            }
            if (null === $this->inputKeys) {
                return false;
            }
            $this->ownKeys = true;
            try {
                return ($this->inputKeys)($data);
            } finally {
                $this->ownKeys = false;
            }
        }
        if ($this->keys->matches($data, 'up') || $this->keys->matches($data, 'down')) {
            $this->list->handleInput($data);

            return true;
        }
        if ($this->keys->matches($data, 'take') && null !== $item = $this->list->getSelectedItem()) {
            $this->input->setValue($item['value'], true);
            $this->show(false);

            return true;
        }
        if ($this->keys->matches($data, 'hide')) {
            $this->dismissed = true;
            $this->show(false);

            return true;
        }

        return false;
    }

    private function refresh(): void
    {
        $matches = $this->dismissed || !$this->input->isFocused() ? [] : $this->getMatches();
        $this->list->setItems(array_map(static fn (string $match) => ['value' => $match, 'label' => $match], $matches));
        $this->show([] !== $matches);
    }

    private function show(bool $open): void
    {
        if ($open === $this->open) {
            return;
        }
        $this->open = $open;
        StyleBridge::set($this->list, (StyleBridge::get($this->list) ?? new Style())->withHidden(!$open));
        $this->setState('open', $open);
        // The <input list> matches :open while its list shows, like a browser
        $this->input->setState('open', $open);
    }
}
