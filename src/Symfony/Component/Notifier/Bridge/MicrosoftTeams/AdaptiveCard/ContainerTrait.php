<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard;

/**
 * The properties shared by Container, Column and TableCell.
 *
 * @internal
 */
trait ContainerTrait
{
    /**
     * Adds an element to the container.
     *
     * @return $this
     */
    public function item(ElementInterface $item): static
    {
        $this->options['items'][] = $item->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function selectAction(SelectActionInterface $selectAction): static
    {
        $this->options['selectAction'] = $selectAction->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function style(ContainerStyle $style): static
    {
        $this->options['style'] = $style->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function verticalContentAlignment(VerticalContentAlignment $verticalContentAlignment): static
    {
        $this->options['verticalContentAlignment'] = $verticalContentAlignment->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function bleed(bool $bleed): static
    {
        $this->options['bleed'] = $bleed;

        return $this;
    }

    /**
     * @return $this
     */
    public function backgroundImage(BackgroundImage|string $backgroundImage): static
    {
        $this->options['backgroundImage'] = \is_string($backgroundImage) ? $backgroundImage : $backgroundImage->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function minHeight(string $minHeight): static
    {
        $this->options['minHeight'] = $minHeight;

        return $this;
    }

    /**
     * @return $this
     */
    public function rtl(bool $rtl): static
    {
        $this->options['rtl'] = $rtl;

        return $this;
    }
}
