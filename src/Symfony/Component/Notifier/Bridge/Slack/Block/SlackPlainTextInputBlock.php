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

final class SlackPlainTextInputBlock extends AbstractSlackBlock
{
    private const LABEL_LIMIT = 150;
    private const ACTION_ID_LIMIT = 255;
    private const PLACEHOLDER_LIMIT = 150;

    public function __construct(string $labelText, string $actionId, ?string $placeholderText = null)
    {
        if (\strlen($labelText) > self::LABEL_LIMIT) {
            throw new \LengthException(\sprintf('Maximum length for the label text is %d characters.', self::LABEL_LIMIT));
        }

        if (\strlen($actionId) > self::ACTION_ID_LIMIT) {
            throw new \LengthException(\sprintf('Maximum length for the action ID is %d characters.', self::ACTION_ID_LIMIT));
        }

        if ($placeholderText && \strlen($placeholderText) > self::PLACEHOLDER_LIMIT) {
            throw new \LengthException(\sprintf('Maximum length for the placeholder text is %d characters.', self::PLACEHOLDER_LIMIT));
        }
        $this->options = [
            'type' => 'input',
            'element' => [
                'type' => 'plain_text_input',
                'action_id' => $actionId,
            ],
            'label' => [
                'type' => 'plain_text',
                'text' => $labelText,
            ],
        ];

        if ($placeholderText) {
            $this->options['element']['placeholder'] = [
                'type' => 'plain_text',
                'text' => $placeholderText,
            ];
        }
    }
}
