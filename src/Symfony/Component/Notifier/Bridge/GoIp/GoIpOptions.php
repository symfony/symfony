<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoIp;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
final class GoIpOptions implements MessageOptionsInterface
{
    private array $options = [];

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function setSimSlot(int $simSlot): static
    {
        $this->options['simSlot'] = $simSlot;

        return $this;
    }

    public function getSimSlot(): ?int
    {
        return $this->options['simSlot'] ?? null;
    }
}
