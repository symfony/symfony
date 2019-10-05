<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack\Block;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class SlackSectionBlock extends AbstractSlackBlock
{
    public function __construct()
    {
        $this->options['type'] = 'section';
    }

    /**
     * @return $this
     */
    public function text(string $text, bool $markdown = true): self
    {
        $this->options['text'] = [
            'type' => $markdown ? 'mrkdwn' : 'plain_text',
            'text' => $text,
        ];

        return $this;
    }

    /**
     * @return $this
     */
    public function accessory(SlackBlockElementInterface $element): self
    {
        $this->options['accessory'] = $element->toArray();

        return $this;
    }
}
