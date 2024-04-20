<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord\Embeds;

use Symfony\Component\Notifier\Exception\LengthException;

/**
 * @author Karoly Gossler <connor@connor.hu>
 */
final class DiscordFooterEmbedObject extends AbstractDiscordEmbedObject
{
    private const TEXT_LIMIT = 2048;

    /**
     * @return $this
     */
    public function text(string $text): static
    {
        if (\strlen($text) > self::TEXT_LIMIT) {
            throw new LengthException(sprintf('Maximum length for the text is %d characters.', self::TEXT_LIMIT));
        }

        $this->options['text'] = $text;

        return $this;
    }

    /**
     * @return $this
     */
    public function iconUrl(string $iconUrl): static
    {
        $this->options['icon_url'] = $iconUrl;

        return $this;
    }

    /**
     * @return $this
     */
    public function proxyIconUrl(string $proxyIconUrl): static
    {
        $this->options['proxy_icon_url'] = $proxyIconUrl;

        return $this;
    }
}
