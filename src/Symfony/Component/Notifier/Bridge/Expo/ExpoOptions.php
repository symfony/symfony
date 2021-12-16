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
    public function title(string $title): self
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function subtitle(string $subtitle): self
    {
        $this->options['subtitle'] = $subtitle;

        return $this;
    }

    /**
     * @return $this
     */
    public function priority(string $priority): self
    {
        $this->options['priority'] = $priority;

        return $this;
    }

    /**
     * @return $this
     */
    public function sound(string $sound): self
    {
        $this->options['sound'] = $sound;

        return $this;
    }

    /**
     * @return $this
     */
    public function badge(int $badge): self
    {
        $this->options['badge'] = $badge;

        return $this;
    }

    /**
     * @return $this
     */
    public function channelId(string $channelId): self
    {
        $this->options['channelId'] = $channelId;

        return $this;
    }

    /**
     * @return $this
     */
    public function categoryId(string $categoryId): self
    {
        $this->options['categoryId'] = $categoryId;

        return $this;
    }

    /**
     * @return $this
     */
    public function mutableContent(bool $mutableContent): self
    {
        $this->options['mutableContent'] = $mutableContent;

        return $this;
    }

    /**
     * @return $this
     */
    public function body(string $body): self
    {
        $this->options['body'] = $body;

        return $this;
    }

    /**
     * @return $this
     */
    public function ttl(int $ttl): self
    {
        $this->options['ttl'] = $ttl;

        return $this;
    }

    /**
     * @return $this
     */
    public function expiration(int $expiration): self
    {
        $this->options['expiration'] = $expiration;

        return $this;
    }

    /**
     * @return $this
     */
    public function data(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
