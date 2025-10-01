<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Primotexto;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Samaël Tomas <samael.tomas@gmail.com>
 */
final class PrimotextoOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function campaignName(string $campaignName): static
    {
        $this->options['campaignName'] = $campaignName;

        return $this;
    }

    /**
     * @return $this
     */
    public function category(string $category): static
    {
        $this->options['category'] = $category;

        return $this;
    }

    /**
     * Planning campaign for a specific date.
     *
     * @return $this
     */
    public function campaignDate(int $timestamp): static
    {
        $this->options['date'] = $timestamp;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
