<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsProxima;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * Options specific to the SMS Proxima API.
 *
 * @author SMS Proxima <contact@sms-proxima.com>
 *
 * @see https://sms-proxima.com/api-sms
 */
final class SmsProximaOptions implements MessageOptionsInterface
{
    private array $options = [];

    /**
     * Send in sandbox mode: SMS is validated but not delivered and no credits are deducted.
     */
    public function sandbox(bool $sandbox): static
    {
        $this->options['sandbox'] = $sandbox ? 1 : 0;

        return $this;
    }

    /**
     * Schedule the SMS for a future date/time.
     * Format: 'Y-m-d H:i' (e.g. '2026-12-25 10:00').
     */
    public function timeToSend(string $dateTime): static
    {
        $this->options['timeToSend'] = $dateTime;

        return $this;
    }

    /**
     * Idempotency key to prevent duplicate sends (UUID v4 recommended).
     */
    public function idempotencyKey(string $key): static
    {
        $this->options['idempotencyKey'] = $key;

        return $this;
    }

    /**
     * Include STOP mention for marketing messages (default: true).
     * Set to false only for transactional/service messages.
     */
    public function stop(bool $stop): static
    {
        $this->options['stop'] = $stop ? 1 : 0;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
