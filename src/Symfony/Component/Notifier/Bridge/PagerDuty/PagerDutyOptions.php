<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\PagerDuty;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
final class PagerDutyOptions implements MessageOptionsInterface
{
    public function __construct(string $routingKey, string $eventAction, string $severity, private array $options = [])
    {
        if (!\in_array($eventAction, ['trigger', 'acknowledge', 'resolve'], true)) {
            throw new InvalidArgumentException('Invalid "event_action" option given.');
        }

        if (!\in_array($severity, ['critical', 'warning', 'error', 'info'], true)) {
            throw new InvalidArgumentException('Invalid "severity" option given.');
        }

        if ($this->options['payload']['timestamp'] ?? null) {
            $timestamp = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $this->options['payload']['timestamp']);
            if (false === $timestamp) {
                throw new InvalidArgumentException('Timestamp date must be in "RFC3339_EXTENDED" format.');
            }
        } else {
            $timestamp = (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED);
        }

        $this->options['routing_key'] = $routingKey;
        $this->options['event_action'] = $eventAction;
        $this->options['payload'] = [
            'severity' => $severity,
            'timestamp' => $timestamp,
        ];

        if ($dedupKey = $options['dedup_key'] ?? null) {
            $this->options['dedup_key'] = $dedupKey;
        }

        if (null === $dedupKey && \in_array($eventAction, ['acknowledge', 'resolve'], true)) {
            throw new InvalidArgumentException('Option "dedup_key" must be set for event actions: "acknowledge" & "resolve".');
        }
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['routing_key'];
    }

    /**
     * @return $this
     */
    public function attachImage(string $src, string $href = '', string $alt = ''): static
    {
        $this->options['images'][] = [
            'src' => $src,
            'href' => $href ?: $src,
            'alt' => $alt,
        ];

        return $this;
    }

    /**
     * @return $this
     */
    public function attachLink(string $href, string $text): static
    {
        $this->options['links'][] = [
            'href' => $href,
            'text' => $text,
        ];

        return $this;
    }

    /**
     * @return $this
     */
    public function attachCustomDetails(array $customDetails): static
    {
        $this->options['payload']['custom_details'] += $customDetails;

        return $this;
    }
}
