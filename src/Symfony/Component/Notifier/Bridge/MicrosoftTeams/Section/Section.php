<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\ActionInterface;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field\Activity;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field\Fact;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field\Image;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#section-fields
 */
final class Section implements SectionInterface
{
    private array $options = [];

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
    public function text(string $text): static
    {
        $this->options['text'] = $text;

        return $this;
    }

    /**
     * @return $this
     */
    public function action(ActionInterface $action): static
    {
        $this->options['potentialAction'][] = $action->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function activity(Activity $activity): static
    {
        foreach ($activity->toArray() as $key => $element) {
            $this->options[$key] = $element;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function image(Image $image): static
    {
        $this->options['images'][] = $image->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function fact(Fact $fact): static
    {
        $this->options['facts'][] = $fact->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function markdown(bool $markdown): static
    {
        $this->options['markdown'] = $markdown;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
