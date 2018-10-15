<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Mercure;

/**
 * Represents an update to send to the hub.
 *
 * @see https://github.com/dunglas/mercure/blob/master/spec/mercure.md#hub
 * @see https://github.com/dunglas/mercure/blob/master/hub/update.go
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class Update
{
    private $topics;
    private $data;
    private $targets;
    private $id;
    private $type;
    private $retry;

    /**
     * @param array|string $topics
     */
    public function __construct($topics, string $data, array $targets = array(), string $id = null, string $type = null, int $retry = null)
    {
        if (!\is_array($topics) && !\is_string($topics)) {
            throw new \InvalidArgumentException('$topics must be an array of strings or a string');
        }

        $this->topics = (array) $topics;
        $this->data = $data;
        $this->targets = $targets;
        $this->id = $id;
        $this->type = $type;
        $this->retry = $retry;
    }

    public function getTopics(): array
    {
        return $this->topics;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getTargets(): array
    {
        return $this->targets;
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
}
