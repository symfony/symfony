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
final class DiscordFieldEmbedObject extends AbstractDiscordEmbedObject
{
    public function name(string $name): self
    {
        $this->options['name'] = $name;

        return $this;
    }

    public function value(string $value): self
    {
        $this->options['value'] = $value;

        return $this;
    }

    public function inline(bool $inline): self
    {
        $this->options['inline'] = $inline;

        return $this;
    }
}
