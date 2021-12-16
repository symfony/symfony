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
    private $options = [];

    /**
     * @return $this
     */
    public function title(string $title): self
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function text(string $text): self
    {
        $this->options['text'] = $text;

        return $this;
    }

    /**
     * @return $this
     */
    public function action(ActionInterface $action): self
    {
        $this->options['potentialAction'][] = $action->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function activity(Activity $activity): self
    {
        foreach ($activity->toArray() as $key => $element) {
            $this->options[$key] = $element;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function image(Image $image): self
    {
        $this->options['images'][] = $image->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function fact(Fact $fact): self
    {
        $this->options['facts'][] = $fact->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function markdown(bool $markdown): self
    {
        $this->options['markdown'] = $markdown;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
