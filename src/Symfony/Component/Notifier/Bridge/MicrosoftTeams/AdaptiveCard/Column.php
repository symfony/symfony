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
 * @see https://adaptivecards.io/explorer/Column.html
 */
final class Column extends ToggleableItem
{
    use ContainerTrait;

    public function __construct()
    {
        $this->options = [
            'type' => 'Column',
        ];
    }

    /**
     * @return $this
     */
    public function fallback(self|FallbackOption $fallback): static
    {
        $this->options['fallback'] = $fallback instanceof FallbackOption ? $fallback->value : $fallback->toArray();

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

    /**
     * @param string|int $width "auto", "stretch", a relative weight as an integer, or a pixel width such as "50px"
     *
     * @return $this
     */
    public function width(string|int $width): static
    {
        $this->options['width'] = $width;

        return $this;
    }
}
