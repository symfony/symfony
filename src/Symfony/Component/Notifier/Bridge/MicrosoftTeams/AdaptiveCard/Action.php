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

abstract class Action extends Item implements ActionInterface
{
    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function iconUrl(string $iconUrl): static
    {
        $this->options['iconUrl'] = $iconUrl;

        return $this;
    }

    /**
     * @return $this
     */
    public function id(string $id): static
    {
        $this->options['id'] = $id;

        return $this;
    }

    /**
     * @return $this
     */
    public function style(ActionStyle $style): static
    {
        $this->options['style'] = $style->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function fallback(ActionInterface|FallbackOption $fallback): static
    {
        $this->options['fallback'] = $fallback instanceof FallbackOption ? $fallback->value : $fallback->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function tooltip(string $tooltip): static
    {
        $this->options['tooltip'] = $tooltip;

        return $this;
    }

    /**
     * @return $this
     */
    public function isEnabled(bool $isEnabled): static
    {
        $this->options['isEnabled'] = $isEnabled;

        return $this;
    }

    /**
     * @return $this
     */
    public function mode(ActionMode $mode): static
    {
        $this->options['mode'] = $mode->value;

        return $this;
    }
}
