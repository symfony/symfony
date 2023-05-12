<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageBird;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class MessageBirdOptions implements MessageOptionsInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getCreatedDatetime(): ?string
    {
        return $this->options['created_datetime'] ?? null;
    }

    public function getDataCoding(): ?string
    {
        return $this->options['data_coding'] ?? null;
    }

    public function getFrom(): ?string
    {
        return $this->options['from'] ?? null;
    }

    public function getGateway(): ?int
    {
        return $this->options['gateway'] ?? null;
    }

    public function getGroupIds(): ?array
    {
        return $this->options['group_ids'] ?? null;
    }

    public function getMClass(): ?int
    {
        return $this->options['m_class'] ?? null;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function getReference(): ?string
    {
        return $this->options['reference'] ?? null;
    }

    public function getReportUrl(): ?string
    {
        return $this->options['report_url'] ?? null;
    }

    public function getScheduledDatetime(): ?string
    {
        return $this->options['scheduled_datetime'] ?? null;
    }

    public function getShortenUrls(): ?bool
    {
        return $this->options['shorten_urls'] ?? null;
    }

    public function getType(): ?string
    {
        return $this->options['type'] ?? null;
    }

    public function getTypeDetails(): ?string
    {
        return $this->options['type_details'] ?? null;
    }

    public function getValidity(): ?int
    {
        return $this->options['validity'] ?? null;
    }

    public function setCreatedDatetime(string $createdDatetime): self
    {
        $this->options['created_datetime'] = $createdDatetime;

        return $this;
    }

    public function setDataCoding(string $dataCoding): self
    {
        $this->options['data_coding'] = $dataCoding;

        return $this;
    }

    public function setFrom(string $from): self
    {
        $this->options['from'] = $from;

        return $this;
    }

    public function setGateway(int $gateway): self
    {
        $this->options['gateway'] = $gateway;

        return $this;
    }

    public function setGroupIds(array $groupIds): self
    {
        $this->options['group_ids'] = $groupIds;

        return $this;
    }

    public function setMClass(int $mClass): self
    {
        $this->options['m_class'] = $mClass;

        return $this;
    }

    public function setRecipientId(string $id): self
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function setReference(string $reference): self
    {
        $this->options['reference'] = $reference;

        return $this;
    }

    public function setReportUrl(string $reportUrl): self
    {
        $this->options['report_url'] = $reportUrl;

        return $this;
    }

    public function setScheduledDatetime(string $scheduledDatetime): self
    {
        $this->options['scheduled_datetime'] = $scheduledDatetime;

        return $this;
    }

    public function setShortenUrls(bool $shortenUrls): self
    {
        $this->options['shorten_urls'] = $shortenUrls;

        return $this;
    }

    public function setType(string $type): self
    {
        $this->options['type'] = $type;

        return $this;
    }

    public function setTypeDetails(string $typeDetails): self
    {
        $this->options['type_details'] = $typeDetails;

        return $this;
    }

    public function setValidity(int $validity): self
    {
        $this->options['validity'] = $validity;

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
