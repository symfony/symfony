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
    public function url(string $url): self
    {
        $this->options['url'] = $url;

        return $this;
    }

    public function proxyUrl(string $proxyUrl): self
    {
        $this->options['proxy_url'] = $proxyUrl;

        return $this;
    }

    public function height(int $height): self
    {
        $this->options['height'] = $height;

        return $this;
    }

    public function width(int $width): self
    {
        $this->options['width'] = $width;

        return $this;
    }
}
