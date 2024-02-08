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

use Symfony\Component\Notifier\Exception\LengthException;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
final class SlackHeaderBlock extends AbstractSlackBlock
{
    private const TEXT_LIMIT = 150;
    private const ID_LIMIT = 255;

    public function __construct(string $text)
    {
        if (\strlen($text) > self::TEXT_LIMIT) {
            throw new LengthException(sprintf('Maximum length for the text is %d characters.', self::TEXT_LIMIT));
        }

        $this->options = [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => $text,
            ],
        ];
    }

    /**
     * @return $this
     */
    public function id(string $id): static
    {
        if (\strlen($id) > self::ID_LIMIT) {
            throw new LengthException(sprintf('Maximum length for the block id is %d characters.', self::ID_LIMIT));
        }

        $this->options['block_id'] = $id;

        return $this;
    }
}
