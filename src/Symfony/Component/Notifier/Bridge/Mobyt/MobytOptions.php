<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mobyt;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Bastien Durand <bdurand-dev@outlook.com>
 */
final class MobytOptions implements MessageOptionsInterface
{
    public const MESSAGE_TYPE_QUALITY_HIGH = 'N';
    public const MESSAGE_TYPE_QUALITY_MEDIUM = 'L';
    public const MESSAGE_TYPE_QUALITY_LOW = 'LL';

    private array $options;

    public function __construct(array $options = [])
    {
        if (isset($options['message_type'])) {
            self::validateMessageType($options['message_type']);
        }

        $this->options = $options;
    }

    public static function fromNotification(Notification $notification): self
    {
        $options = new self();
        match ($notification->getImportance()) {
            Notification::IMPORTANCE_HIGH, Notification::IMPORTANCE_URGENT => $options->messageType(self::MESSAGE_TYPE_QUALITY_HIGH),
            Notification::IMPORTANCE_MEDIUM => $options->messageType(self::MESSAGE_TYPE_QUALITY_MEDIUM),
            Notification::IMPORTANCE_LOW => $options->messageType(self::MESSAGE_TYPE_QUALITY_LOW),
            default => $options->messageType(self::MESSAGE_TYPE_QUALITY_HIGH),
        };

        return $options;
    }

    public function toArray(): array
    {
        $options = $this->options;
        unset($options['message'], $options['recipient']);

        return $options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient'] ?? null;
    }

    public function messageType(string $type): void
    {
        self::validateMessageType($type);

        $this->options['message_type'] = $type;
    }

    public static function validateMessageType(string $type): string
    {
        if (!\in_array($type, $supported = [self::MESSAGE_TYPE_QUALITY_HIGH, self::MESSAGE_TYPE_QUALITY_MEDIUM, self::MESSAGE_TYPE_QUALITY_LOW], true)) {
            throw new InvalidArgumentException(sprintf('The message type "%s" is not supported; supported message types are: "%s"', $type, implode('", "', $supported)));
        }

        return $type;
    }
}
