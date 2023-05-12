<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GatewayApi;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class GatewayApiOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getClass(): ?int
    {
        return $this->options['class'] ?? null;
    }

    public function getUserRef(): ?string
    {
        return $this->options['user_ref'] ?? null;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->options['callback_url'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->options['from'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function setClass(int $class): self
    {
        $this->options['class'] = $class;

        return $this;
    }

    public function setUserRef(string $userRef): self
    {
        $this->options['user_ref'] = $userRef;

        return $this;
    }

    public function setCallbackUrl(string $callbackUrl): self
    {
        $this->options['callback_url'] = $callbackUrl;

        return $this;
    }

    public function setFrom(string $from): self
    {
        $this->options['from'] = $from;

        return $this;
    }

    public function setRecipientId(string $id): self
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function toArray(): array
    {
        $options = $this->options;
        if (isset($options['recipient_id'])) {
            unset($options['recipient_id']);
        }

        return $options;
    }
}
