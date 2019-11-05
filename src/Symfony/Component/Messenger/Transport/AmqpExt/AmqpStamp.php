<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * @author Guillaume Gammelin <ggammelin@gmail.com>
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
final class AmqpStamp implements NonSendableStampInterface
{
    private $routingKey;
    private $flags;
    private $attributes;

    public function __construct(string $routingKey = null, int $flags = AMQP_NOPARAM, array $attributes = [])
    {
        $this->routingKey = $routingKey;
        $this->flags = $flags;
        $this->attributes = $attributes;
    }

    public function getRoutingKey(): ?string
    {
        return $this->routingKey;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public static function createFromAmqpEnvelope(\AMQPEnvelope $amqpEnvelope, self $previousStamp = null): self
    {
        $attr = $previousStamp->attributes ?? [];

        $attr['headers'] = $attr['headers'] ?? $amqpEnvelope->getHeaders();
        $attr['content_type'] = $attr['content_type'] ?? $amqpEnvelope->getContentType();
        $attr['content_encoding'] = $attr['content_encoding'] ?? $amqpEnvelope->getContentEncoding();
        $attr['delivery_mode'] = $attr['delivery_mode'] ?? $amqpEnvelope->getDeliveryMode();
        $attr['priority'] = $attr['priority'] ?? $amqpEnvelope->getPriority();
        $attr['timestamp'] = $attr['timestamp'] ?? $amqpEnvelope->getTimestamp();
        $attr['app_id'] = $attr['app_id'] ?? $amqpEnvelope->getAppId();
        $attr['message_id'] = $attr['message_id'] ?? $amqpEnvelope->getMessageId();
        $attr['user_id'] = $attr['user_id'] ?? $amqpEnvelope->getUserId();
        $attr['expiration'] = $attr['expiration'] ?? $amqpEnvelope->getExpiration();
        $attr['type'] = $attr['type'] ?? $amqpEnvelope->getType();
        $attr['reply_to'] = $attr['reply_to'] ?? $amqpEnvelope->getReplyTo();

        return new self($previousStamp->routingKey ?? $amqpEnvelope->getRoutingKey(), $previousStamp->flags ?? AMQP_NOPARAM, $attr);
    }

    public static function createWithAttributes(array $attributes, self $previousStamp = null): self
    {
        return new self(
            $previousStamp->routingKey ?? null,
            $previousStamp->flags ?? AMQP_NOPARAM,
            array_merge($previousStamp->attributes ?? [], $attributes)
        );
    }
}
