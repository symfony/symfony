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
 * @author Christophe Vergne <christophe.vergne@gmail.com>
 */
final class SlackButtonBlockElement extends AbstractSlackBlockElement
{
    public function __construct(string $text, string $url, ?string $style = null)
    {
        $this->options = [
            'type' => 'button',
            'text' => [
                'type' => 'plain_text',
                'text' => $text,
            ],
            'url' => $url,
        ];

        if ($style) {
            // primary or danger
            $this->options['style'] = $style;
        }
    }
}
