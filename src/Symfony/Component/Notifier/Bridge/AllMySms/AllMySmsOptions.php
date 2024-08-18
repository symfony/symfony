<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AllMySms;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class AllMySmsOptions implements MessageOptionsInterface
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
    public function alerting(int $alerting): static
    {
        $this->options['alerting'] = $alerting;

        return $this;
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
    public function cliMsgId(string $cliMsgId): static
    {
        $this->options['cliMsgId'] = $cliMsgId;

        return $this;
    }

    /**
     * @return $this
     */
    public function date(string $date): static
    {
        $this->options['date'] = $date;

        return $this;
    }

    /**
     * @return $this
     */
    public function simulate(int $simulate): static
    {
        $this->options['simulate'] = $simulate;

        return $this;
    }

    /**
     * @return $this
     */
    public function uniqueIdentifier(string $uniqueIdentifier): static
    {
        $this->options['uniqueIdentifier'] = $uniqueIdentifier;

        return $this;
    }

    /**
     * @return $this
     */
    public function verbose(int $verbose): static
    {
        $this->options['verbose'] = $verbose;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
