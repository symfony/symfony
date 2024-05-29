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
final class DiscordFieldEmbedObject extends AbstractDiscordEmbedObject
{
    private const NAME_LIMIT = 256;
    private const VALUE_LIMIT = 1024;

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
    public function value(string $value): static
    {
        if (\strlen($value) > self::VALUE_LIMIT) {
            throw new LengthException(sprintf('Maximum length for the value is %d characters.', self::VALUE_LIMIT));
        }

        $this->options['value'] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function inline(bool $inline): static
    {
        $this->options['inline'] = $inline;

        return $this;
    }
}
