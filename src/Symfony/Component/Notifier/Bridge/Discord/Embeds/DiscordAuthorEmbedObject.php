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
final class DiscordAuthorEmbedObject extends AbstractDiscordEmbedObject
{
    private const NAME_LIMIT = 256;

    /**
     * @return $this
     */
    public function name(string $name): static
    {
        if (\strlen($name) > self::NAME_LIMIT) {
            throw new LengthException(sprintf('Maximum length for the name is %d characters.', self::NAME_LIMIT));
        }

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
