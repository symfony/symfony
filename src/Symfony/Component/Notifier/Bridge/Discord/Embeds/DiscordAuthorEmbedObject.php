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
final class DiscordAuthorEmbedObject extends AbstractDiscordEmbedObject
{
    /**
     * @return $this
     */
    public function name(string $name): static
    {
        $this->options['name'] = $name;

        return $this;
    }

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
