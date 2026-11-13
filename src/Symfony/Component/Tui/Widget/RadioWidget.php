<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

/**
 * One option of a group of which only one can be checked, chosen with Space.
 *
 * It draws "(•)" or "( )". Radios sharing a {@see RadioGroup} uncheck each
 * other; unlike a checkbox, a radio cannot be unchecked by pressing it.
 *
 * @experimental
 */
class RadioWidget extends CheckboxWidget
{
    public function __construct(
        bool $checked = false,
        string $value = 'on',
        private readonly ?RadioGroup $group = null,
    ) {
        parent::__construct(false, $value);
        $group?->add($this);
        if ($checked) {
            $this->setChecked(true, false);
        } elseif (null === $group) {
            $this->setState('indeterminate');
        }
    }

    public function getGroup(): ?RadioGroup
    {
        return $this->group;
    }

    public function setChecked(bool $checked, bool $dispatch = true): static
    {
        if ($checked && !$this->isChecked()) {
            $this->group?->uncheckOthers($this, $dispatch);
        }

        parent::setChecked($checked, $dispatch);
        // A radio alone is its own group
        null !== $this->group ? $this->group->updateIndeterminate() : $this->setState('indeterminate', !$checked);

        return $this;
    }

    public function toggle(): bool
    {
        if ($this->hasState('disabled') || $this->isChecked()) {
            return false;
        }

        $this->setChecked(true);

        return true;
    }

    protected function getMark(): string
    {
        return $this->isChecked() ? '◉' : '○';
    }

    protected function getGlyph(): string
    {
        return $this->isChecked() ? '(•)' : '( )';
    }
}
