<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#section-fields
 */
final class Activity
{
    private $options = [];

    /**
     * @return $this
     */
    public function image(string $imageUrl): self
    {
        $this->options['activityImage'] = $imageUrl;

        return $this;
    }

    /**
     * @return $this
     */
    public function title(string $title): self
    {
        $this->options['activityTitle'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function subtitle(string $subtitle): self
    {
        $this->options['activitySubtitle'] = $subtitle;

        return $this;
    }

    /**
     * @return $this
     */
    public function text(string $text): self
    {
        $this->options['activityText'] = $text;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
