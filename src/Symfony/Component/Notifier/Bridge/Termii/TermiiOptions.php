<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Termii;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class TermiiOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function channel(string $channel): static
    {
        $this->options['channel'] = $channel;

        return $this;
    }

    /**
     * @return $this
     */
    public function media(string $url, string $caption = ''): static
    {
        $this->options['media'] = [
            'url' => $url,
            'caption' => $caption,
        ];

        return $this;
    }

    /**
     * @return $this
     */
    public function type(string $type): static
    {
        $this->options['type'] = $type;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
