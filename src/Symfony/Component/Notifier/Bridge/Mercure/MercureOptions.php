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

    /**
     * @param string|string[]|null $topics
     * @param array{
     *     badge?: string,
     *     body?: string,
     *     data?: mixed,
     *     dir?: 'auto'|'ltr'|'rtl',
     *     icon?: string,
     *     image?: string,
     *     lang?: string,
     *     renotify?: bool,
     *     requireInteraction?: bool,
     *     silent?: bool,
     *     tag?: string,
     *     timestamp?: int,
     *     vibrate?: int|list<int>,
     * }|null $content
     */
    public function __construct(
        string|array|null $topics = null,
        private bool $private = false,
        private ?string $id = null,
        private ?string $type = null,
        private ?int $retry = null,
        private ?array $content = null,
    ) {
        $this->topics = null !== $topics ? (array) $topics : null;
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

    /**
     * @return array{
     *      badge?: string,
     *      body?: string,
     *      data?: mixed,
     *      dir?: 'auto'|'ltr'|'rtl',
     *      icon?: string,
     *      image?: string,
     *      lang?: string,
     *      renotify?: bool,
     *      requireInteraction?: bool,
     *      silent?: bool,
     *      tag?: string,
     *      timestamp?: int,
     *      vibrate?: int|list<int>,
     *  }|null
     */
    public function getContent(): ?array
    {
        return $this->content;
    }

    public function toArray(): array
    {
        return [
            'topics' => $this->topics,
            'private' => $this->private,
            'id' => $this->id,
            'type' => $this->type,
            'retry' => $this->retry,
            'content' => $this->content,
        ];
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
