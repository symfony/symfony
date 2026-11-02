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

abstract class Element extends ToggleableItem implements ElementInterface
{
    /**
     * @return $this
     */
    public function fallback(ElementInterface|FallbackOption $fallback): static
    {
        $this->options['fallback'] = $fallback instanceof FallbackOption ? $fallback->value : $fallback->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function height(BlockElementHeight $height): static
    {
        $this->options['height'] = $height->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function separator(bool $separator): static
    {
        $this->options['separator'] = $separator;

        return $this;
    }

    /**
     * @return $this
     */
    public function spacing(Spacing $spacing): static
    {
        $this->options['spacing'] = $spacing->value;

        return $this;
    }
}
