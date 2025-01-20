<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pushy;

use Symfony\Component\Notifier\Bridge\Pushy\Enum\InterruptionLevel;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Joseph Bielawski <stloyd@gmail.com>
 *
 * @see https://pushy.me/docs/api/send-notifications
 */
final class PushyOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public static function fromNotification(Notification $notification): self
    {
        $options = new self();
        $options->interruptionLevel(
            match ($notification->getImportance()) {
                Notification::IMPORTANCE_URGENT => InterruptionLevel::CRITICAL,
                Notification::IMPORTANCE_HIGH => InterruptionLevel::TIME_SENSITIVE,
                Notification::IMPORTANCE_MEDIUM => InterruptionLevel::ACTIVE,
                Notification::IMPORTANCE_LOW => InterruptionLevel::PASSIVE,
            }
        );

        return $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['to'] ?? null;
    }

    /**
     * @see https://pushy.me/docs/api/send-notifications#request-schema
     *
     * @param string|string[] $to
     *
     * @return $this
     */
    public function to(string|array $to): static
    {
        $this->options['to'] = $to;

        return $this;
    }

    /**
     * @see https://pushy.me/docs/api/send-notifications#request-schema
     *
     * @return $this
     */
    public function contentAvailable(bool $bool): static
    {
        $this->options['content_available'] = $bool;

        return $this;
    }

    /**
     * @see https://pushy.me/docs/api/send-notifications#request-schema
     *
     * @return $this
     */
    public function mutableContent(bool $bool): static
    {
        $this->options['mutable_content'] = $bool;

        return $this;
    }

    /**
     * @see https://pushy.me/docs/api/send-notifications#request-schema
     *
     * @return $this
     */
    public function ttl(int $seconds): static
    {
        if ($seconds > (86400 * 365)) {
            throw new InvalidArgumentException('Pushy notification time to live cannot exceed 365 days.');
        }

        $this->options['time_to_live'] = $seconds;

        return $this;
    }

    /**
     * @see https://pushy.me/docs/api/send-notifications#request-schema
     *
     * @return $this
     */
    public function schedule(int $seconds): static
    {
        if (false === \DateTime::createFromFormat('U', $seconds)) {
            throw new InvalidArgumentException('Pushy notification schedule time must be correct Unix timestamp.');
        }

        if (\DateTime::createFromFormat('U', $seconds) >= new \DateTime('+1 year')) {
            throw new InvalidArgumentException('Pushy notification schedule time cannot exceed 1 year.');
        }

        $this->options['schedule'] = $seconds;

        return $this;
    }

    /**
     * @see https://pushy.me/docs/api/send-notifications#request-schema
     *
     * @return $this
     */
    public function collapseKey(string $collapseKey): static
    {
        if (32 < \strlen($collapseKey)) {
            throw new InvalidArgumentException('Pushy notification collapse key cannot be longer than 32 characters.');
        }

        $this->options['collapse_key'] = $collapseKey;

        return $this;
    }

    /**
     * @return $this
     */
    public function body(string $body): static
    {
        $this->options['notification']['body'] = $body;

        return $this;
    }

    /**
     * @see https://pushy.me/docs/api/send-notifications#request-schema
     *
     * @return $this
     */
    public function badge(int $badge): static
    {
        $this->options['notification']['badge'] = $badge;

        return $this;
    }

    /**
     * @see https://pushy.me/docs/api/send-notifications#request-schema
     *
     * @return $this
     */
    public function threadId(int $threadId): static
    {
        $this->options['notification']['thread_id'] = $threadId;

        return $this;
    }

    /**
     * @see https://pushy.me/docs/api/send-notifications#request-schema
     *
     * @return $this
     */
    public function interruptionLevel(InterruptionLevel $interruptionLevel): static
    {
        $this->options['notification']['interruption_level'] = $interruptionLevel->value;

        return $this;
    }
}
