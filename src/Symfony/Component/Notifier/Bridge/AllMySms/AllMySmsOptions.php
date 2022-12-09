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
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getAlerting(): ?int
    {
        return $this->options['alerting'] ?? null;
    }

    public function getCampaignName(): ?string
    {
        return $this->options['campaign_name'] ?? null;
    }

    public function getCliMsgId(): ?string
    {
        return $this->options['cli_msg_id'] ?? null;
    }

    public function getDate(): ?string
    {
        return $this->options['date'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->options['from'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function getSimulate(): ?int
    {
        return $this->options['simulate'] ?? null;
    }

    public function getUniqueIdentifier(): ?string
    {
        return $this->options['unique_identifier'] ?? null;
    }

    public function getVerbose(): ?int
    {
        return $this->options['verbose'] ?? null;
    }

    public function setAlerting(int $alerting): self
    {
        $this->options['alerting'] = $alerting;

        return $this;
    }

    public function setCampaignName(string $campaignName): self
    {
        $this->options['campaign_name'] = $campaignName;

        return $this;
    }

    public function setCliMsgId(string $cliMsgId): self
    {
        $this->options['cli_msg_id'] = $cliMsgId;

        return $this;
    }

    public function setDate(string $date): self
    {
        $this->options['date'] = $date;

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

    public function setSimulate(int $simulate): self
    {
        $this->options['simulate'] = $simulate;

        return $this;
    }

    public function setUniqueIdentifier(string $uniqueIdentifier): self
    {
        $this->options['unique_identifier'] = $uniqueIdentifier;

        return $this;
    }

    public function setVerbose(int $verbose): self
    {
        $this->options['verbose'] = $verbose;

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
