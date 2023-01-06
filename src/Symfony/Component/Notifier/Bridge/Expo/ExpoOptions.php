<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Expo;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Imad ZAIRIG <https://github.com/zairigimad>
 *
 * @see https://docs.expo.dev/push-notifications/sending-notifications/
 */
final class ExpoOptions implements MessageOptionsInterface
{
    private $to;

    /**
     * @see https://docs.expo.dev/push-notifications/sending-notifications/#message-request-format
     */
    protected $options;

    private $data;

    public function __construct(string $to, array $options = [], array $data = [])
    {
        $this->to = $to;
        $this->options = $options;
        $this->data = $data;
    }

    public function toArray(): array
    {
        return array_merge(
            $this->options,
            [
                'to' => $this->to,
                'data' => $this->data,
            ]
        );
    }

    public function getRecipientId(): ?string
    {
        return $this->to;
    }

    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function subtitle(string $subtitle): static
    {
        $this->options['subtitle'] = $subtitle;

        return $this;
    }

    /**
     * @return $this
     */
    public function priority(string $priority): static
    {
        $this->options['priority'] = $priority;

        return $this;
    }

    /**
     * @return $this
     */
    public function sound(string $sound): static
    {
        $this->options['sound'] = $sound;

        return $this;
    }

    /**
     * @return $this
     */
    public function badge(int $badge): static
    {
        $this->options['badge'] = $badge;

        return $this;
    }

    /**
     * @return $this
     */
    public function channelId(string $channelId): static
    {
        $this->options['channelId'] = $channelId;

        return $this;
    }

    /**
     * @return $this
     */
    public function categoryId(string $categoryId): static
    {
        $this->options['categoryId'] = $categoryId;

        return $this;
    }

    /**
     * @return $this
     */
    public function mutableContent(bool $mutableContent): static
    {
        $this->options['mutableContent'] = $mutableContent;

        return $this;
    }

    /**
     * @return $this
     */
    public function body(string $body): static
    {
        $this->options['body'] = $body;

        return $this;
    }

    /**
     * @return $this
     */
    public function ttl(int $ttl): static
    {
        $this->options['ttl'] = $ttl;

        return $this;
    }

    /**
     * @return $this
     */
    public function expiration(int $expiration): static
    {
        $this->options['expiration'] = $expiration;

        return $this;
    }

    /**
     * @return $this
     */
    public function data(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
