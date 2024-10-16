<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineBot;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @see https://developers.line.biz/en/reference/messaging-api/#send-push-message-request-body
 *
 * @author Yi-Jyun Pan <me@pan93.com>
 */
final class LineBotOptions implements MessageOptionsInterface
{
    /**
     * @param string|null $to
     * @param array<string, mixed>[] $messages
     * @param bool|null $notificationDisabled
     * @param string[]|null $customAggregationUnits
     */
    public function __construct(
        private ?string $to = null,
        private array $messages = [],
        private ?bool $notificationDisabled = null,
        private ?array $customAggregationUnits = null,
    ) {
    }

    public static function fromNotification(Notification $notification): static
    {
        $message = $notification->getSubject();

        if ($notification->getEmoji()) {
            $message = $notification->getEmoji().' '.$message;
        }

        if ($notification->getContent()) {
            $message .= "\n".$notification->getContent();
        }

        return (new self())
            ->addMessage([
                'type' => 'text',
                'text' => $message,
            ]);
    }

    /**
     * @return array{
     *     to: string|null,
     *     messages: array<string, mixed>[],
     *     notificationDisabled: bool|null,
     *     customAggregationUnits: string[]|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'messages' => $this->messages,
            'notificationDisabled' => $this->notificationDisabled,
            'customAggregationUnits' => $this->customAggregationUnits,
        ];
    }

    public function getRecipientId(): ?string
    {
        return $this->to;
    }

    /**
     * ID of the target recipient.
     *
     * @return $this
     */
    public function to(string $userId): static
    {
        $this->to = $userId;

        return $this;
    }

    /**
     * Messages to send. Max: 5.
     *
     * @see https://developers.line.biz/en/reference/messaging-api/#message-objects
     *
     * @param array<string, mixed> $message the message object to add to
     *
     * @return $this
     */
    public function addMessage(array $message): static
    {
        if (\count($this->messages) >= 5) {
            throw new LogicException('You can only add up to 5 messages.');
        }

        $this->messages[] = $message;

        return $this;
    }

    /**
     * Whether to send notification. `true` to not receive a push notification
     * when the message is sent.
     *
     * @param bool $disable whether to disable notification
     *
     * @return $this
     */
    public function disableNotification(bool $disable): static
    {
        $this->notificationDisabled = $disable;

        return $this;
    }

    /**
     * Name of aggregation unit.
     *
     * @param ?string[] $units the name of the aggregation unit
     *
     * @return $this
     */
    public function customAggregationUnits(?array $units): static
    {
        if (null !== $units && 0 == \count($units)) {
            throw new LogicException('You must provide at least one aggregation unit if units is not null.');
        }

        $this->customAggregationUnits = $units;

        return $this;
    }
}
