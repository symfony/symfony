<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class EsendexOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getAccountReference(): ?string
    {
        return $this->options['account_reference'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->options['from'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function setAccountReference(string $accountReference): self
    {
        $this->options['account_reference'] = $accountReference;

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
