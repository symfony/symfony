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
    public function text(string $text, bool $markdown = true, bool $emoji = true, bool $verbatim = false): static
    {
        $this->options['text'] = [
            'type' => $markdown ? 'mrkdwn' : 'plain_text',
            'text' => $text,
        ];

        // verbatim is only available for markdown
        if ($markdown) {
            $this->options['text']['verbatim'] = $verbatim;
        } else {
            $this->options['text']['emoji'] = $emoji;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function field(string $text, bool $markdown = true, bool $emoji = true, bool $verbatim = false): static
    {
        if (10 === \count($this->options['fields'] ?? [])) {
            throw new \LogicException('Maximum number of fields should not exceed 10.');
        }

        $field = [
            'type' => $markdown ? 'mrkdwn' : 'plain_text',
            'text' => $text,
        ];

        // verbatim is only available for markdown
        if ($markdown) {
            $field['verbatim'] = $verbatim;
        } else {
            $field['emoji'] = $emoji;
        }

        $this->options['fields'][] = $field;

        return $this;
    }

    /**
     * @return $this
     */
    public function accessory(SlackBlockElementInterface $element): static
    {
        $this->options['accessory'] = $element->toArray();

        return $this;
    }
}
