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

    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public static function fromNotification(Notification $notification): self
    {
        $options = new self();
        switch ($notification->getImportance()) {
            case Notification::IMPORTANCE_HIGH:
            case Notification::IMPORTANCE_URGENT:
                $options->messageType(self::MESSAGE_TYPE_QUALITY_HIGH);
                break;
            case Notification::IMPORTANCE_MEDIUM:
                $options->messageType(self::MESSAGE_TYPE_QUALITY_MEDIUM);
                break;
            case Notification::IMPORTANCE_LOW:
                $options->messageType(self::MESSAGE_TYPE_QUALITY_LOW);
                break;
            default:
                $options->messageType(self::MESSAGE_TYPE_QUALITY_HIGH);
        }

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

    public function messageType(string $type)
    {
        $this->options['message_type'] = $type;
    }
}
