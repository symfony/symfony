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

/**
 * The radios of which only one can be checked, like the ones of an HTML form sharing a name.
 *
 * @experimental
 */
final class RadioGroup
{
    /** @var list<\WeakReference<RadioWidget>> */
    private array $radios = [];

    public function __construct(public readonly string $name = '')
    {
    }

    /**
     * @internal called by RadioWidget
     */
    public function add(RadioWidget $radio): void
    {
        $this->radios[] = \WeakReference::create($radio);
        $this->updateIndeterminate();
    }

    /**
     * @return list<RadioWidget>
     */
    public function all(): array
    {
        return array_values(array_filter(array_map(static fn (\WeakReference $radio) => $radio->get(), $this->radios)));
    }

    public function getChecked(): ?RadioWidget
    {
        foreach ($this->all() as $radio) {
            if ($radio->isChecked()) {
                return $radio;
            }
        }

        return null;
    }

    /**
     * Marks every radio indeterminate while none of the group is checked, like CSS :indeterminate.
     *
     * @internal called by RadioWidget
     */
    public function updateIndeterminate(): void
    {
        $indeterminate = null === $this->getChecked();
        foreach ($this->all() as $radio) {
            $radio->setState('indeterminate', $indeterminate);
        }
    }

    /**
     * @internal called by RadioWidget
     */
    public function uncheckOthers(RadioWidget $checked, bool $dispatch): void
    {
        foreach ($this->all() as $radio) {
            if ($radio !== $checked && $radio->isChecked()) {
                $radio->setChecked(false, $dispatch);
            }
        }
    }
}
