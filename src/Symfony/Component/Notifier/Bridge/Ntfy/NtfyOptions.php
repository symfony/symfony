<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Ntfy;

use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Mickael Perraud <mikaelkael.fr@gmail.com>
 */
final class NtfyOptions implements MessageOptionsInterface
{
    public const PRIORITY_URGENT = 5;
    public const PRIORITY_HIGH = 4;
    public const PRIORITY_DEFAULT = 3;
    public const PRIORITY_LOW = 2;
    public const PRIORITY_MIN = 1;

    private ClockInterface $clock;

    public function __construct(
        private array $options = [],
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? Clock::get();
    }

    public static function fromNotification(Notification $notification): self
    {
        $options = new self();
        $options->setTitle($notification->getSubject());
        $options->setMessage($notification->getContent());
        $options->setStringPriority($notification->getImportance());
        $options->addTag($notification->getEmoji());

        return $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    public function setMessage(string $message): self
    {
        $this->options['message'] = $message;

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->options['title'] = $title;

        return $this;
    }

    public function setStringPriority(string $priority): self
    {
        return match ($priority) {
            Notification::IMPORTANCE_URGENT => $this->setPriority(self::PRIORITY_URGENT),
            Notification::IMPORTANCE_HIGH => $this->setPriority(self::PRIORITY_HIGH),
            Notification::IMPORTANCE_LOW => $this->setPriority(self::PRIORITY_LOW),
            default => $this->setPriority(self::PRIORITY_DEFAULT),
        };
    }

    public function setPriority(int $priority): self
    {
        if (\in_array($priority, [
            self::PRIORITY_MIN, self::PRIORITY_LOW, self::PRIORITY_DEFAULT, self::PRIORITY_HIGH, self::PRIORITY_URGENT,
        ])) {
            $this->options['priority'] = $priority;
        }

        return $this;
    }

    public function addTag(string $tag): self
    {
        $this->options['tags'][] = $tag;

        return $this;
    }

    public function setTags(array $tags): self
    {
        $this->options['tags'] = $tags;

        return $this;
    }

    public function setDelay(\DateTimeInterface $dateTime): self
    {
        if ($dateTime > $this->clock->now()) {
            $this->options['delay'] = (string) $dateTime->getTimestamp();
        } else {
            throw new LogicException('Delayed date must be defined in the future.');
        }

        return $this;
    }

    public function setActions(array $actions): self
    {
        $this->options['actions'] = $actions;

        return $this;
    }

    public function addAction(array $action): self
    {
        $this->options['actions'][] = $action;

        return $this;
    }

    public function setClick(string $url): self
    {
        $this->options['click'] = $url;

        return $this;
    }

    public function setAttachment(string $attachment): self
    {
        $this->options['attach'] = $attachment;

        return $this;
    }

    public function setFilename(string $filename): self
    {
        $this->options['filename'] = $filename;

        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->options['email'] = $email;

        return $this;
    }

    public function setCache(bool $enable): self
    {
        if (!$enable) {
            $this->options['cache'] = 'no';
        } else {
            unset($this->options['cache']);
        }

        return $this;
    }

    public function setFirebase(bool $enable): self
    {
        if (!$enable) {
            $this->options['firebase'] = 'no';
        } else {
            unset($this->options['firebase']);
        }

        return $this;
    }
}
