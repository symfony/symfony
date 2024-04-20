<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord;

use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbedInterface;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Karoly Gossler <connor@connor.hu>
 */
final class DiscordOptions implements MessageOptionsInterface
{
    private array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): string
    {
        return '';
    }

    /**
     * @return $this
     */
    public function username(string $username): static
    {
        $this->options['username'] = $username;

        return $this;
    }

    /**
     * @return $this
     */
    public function avatarUrl(string $avatarUrl): static
    {
        $this->options['avatar_url'] = $avatarUrl;

        return $this;
    }

    /**
     * @return $this
     */
    public function tts(bool $tts): static
    {
        $this->options['tts'] = $tts;

        return $this;
    }

    /**
     * @return $this
     */
    public function addEmbed(DiscordEmbedInterface $embed): static
    {
        if (!isset($this->options['embeds'])) {
            $this->options['embeds'] = [];
        }

        if (\count($this->options['embeds']) >= 10) {
            throw new LogicException(sprintf('The "%s" only supports max 10 embeds.', __CLASS__));
        }

        $this->options['embeds'][] = $embed->toArray();

        return $this;
    }
}
