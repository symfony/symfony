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

final class MercureOptions implements MessageOptionsInterface
{
    private $topics;
    private $private;
    private $id;
    private $type;
    private $retry;

    /**
     * @param string|string[]|null $topics
     */
    public function __construct($topics = null, bool $private = false, ?string $id = null, ?string $type = null, ?int $retry = null)
    {
        if (null !== $topics && !\is_array($topics) && !\is_string($topics)) {
            throw new \TypeError(sprintf('"%s()" expects parameter 1 to be an array of strings, a string or null, "%s" given.', __METHOD__, get_debug_type($topics)));
        }

        $this->topics = null !== $topics ? (array) $topics : null;
        $this->private = $private;
        $this->id = $id;
        $this->type = $type;
        $this->retry = $retry;
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

    public function toArray(): array
    {
        return [
            'topics' => $this->topics,
            'private' => $this->private,
            'id' => $this->id,
            'type' => $this->type,
            'retry' => $this->retry,
        ];
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
