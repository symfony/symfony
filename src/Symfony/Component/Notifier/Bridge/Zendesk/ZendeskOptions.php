<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zendesk;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
final class ZendeskOptions implements MessageOptionsInterface
{
    private ?string $priority;

    public function __construct(string $priority = null)
    {
        $this->priority = $priority;
    }

    public function toArray(): array
    {
        return [
            'priority' => $this->priority,
        ];
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function priority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }
}
