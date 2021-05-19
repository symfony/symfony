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

final class SlackImageBlockElement extends AbstractSlackBlockElement
{
    public function __construct(string $url, string $text)
    {
        $this->options = [
            'type' => 'image',
            'image_url' => $url,
            'alt_text' => $text,
        ];
    }
}
