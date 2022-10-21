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

final class SlackContextBlock extends AbstractSlackBlock
{
    private const ELEMENT_LIMIT = 10;

    public function __construct()
    {
        $this->options['type'] = 'context';
    }

    /**
     * @return $this
     */
    public function text(string $text, bool $markdown = true, bool $emoji = true, bool $verbatim = false): static
    {
        if (self::ELEMENT_LIMIT === \count($this->options['elements'] ?? [])) {
            throw new \LogicException(sprintf('Maximum number of elements should not exceed %d.', self::ELEMENT_LIMIT));
        }

        $element = [
            'type' => $markdown ? 'mrkdwn' : 'plain_text',
            'text' => $text,
        ];
        if ($markdown) {
            $element['verbatim'] = $verbatim;
        } else {
            $element['emoji'] = $emoji;
        }
        $this->options['elements'][] = $element;

        return $this;
    }

    /**
     * @return $this
     */
    public function image(string $url, string $text): static
    {
        if (self::ELEMENT_LIMIT === \count($this->options['elements'] ?? [])) {
            throw new \LogicException(sprintf('Maximum number of elements should not exceed %d.', self::ELEMENT_LIMIT));
        }

        $this->options['elements'][] = [
            'type' => 'image',
            'image_url' => $url,
            'alt_text' => $text,
        ];

        return $this;
    }

    /**
     * @return $this
     */
    public function id(string $id): static
    {
        $this->options['block_id'] = $id;

        return $this;
    }
}
