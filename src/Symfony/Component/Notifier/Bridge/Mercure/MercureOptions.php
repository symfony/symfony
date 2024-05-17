<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mercure;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class MercureOptions implements MessageOptionsInterface
{
    private ?array $topics;
    private bool $private;
    private ?string $id;
    private ?string $type;
    private ?int $retry;
    private ?string $body;
    private ?string $icon;
    private ?string $tag;
    private bool $renotify;

    /**
     * @param string|string[]|null $topics
     */
    public function __construct(string|array|null $topics = null, bool $private = false, ?string $id = null, ?string $type = null, ?int $retry = null, ?string $body = null, ?string $icon = null, ?string $tag = null, ?bool $renotify = false)
    {
        $this->topics = null !== $topics ? (array) $topics : null;
        $this->private = $private;
        $this->id = $id;
        $this->type = $type;
        $this->retry = $retry;
        $this->body = $body;
        $this->icon = $icon;
        $this->tag = $tag;
        $this->renotify = $renotify;
    }

    /**
     * @return string[]|null
     */
    public function getTopics(): ?array
    {
        return $this->topics;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getRetry(): ?int
    {
        return $this->retry;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function isRenotify(): bool
    {
        return $this->renotify;
    }

    public function toArray(): array
    {
        return [
            'topics' => $this->topics,
            'private' => $this->private,
            'id' => $this->id,
            'type' => $this->type,
            'retry' => $this->retry,
            'body' => $this->body,
            'icon' => $this->icon,
            'tag' => $this->tag,
            'renotify' => $this->renotify,
        ];
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
