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

/**
 * @author Karoly Gossler <connor@connor.hu>
 */
class DiscordMediaEmbedObject extends AbstractDiscordEmbedObject
{
    /**
     * @return $this
     */
    public function url(string $url): static
    {
        $this->options['url'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function proxyUrl(string $proxyUrl): static
    {
        $this->options['proxy_url'] = $proxyUrl;

        return $this;
    }

    /**
     * @return $this
     */
    public function height(int $height): static
    {
        $this->options['height'] = $height;

        return $this;
    }

    /**
     * @return $this
     */
    public function width(int $width): static
    {
        $this->options['width'] = $width;

        return $this;
    }
}
