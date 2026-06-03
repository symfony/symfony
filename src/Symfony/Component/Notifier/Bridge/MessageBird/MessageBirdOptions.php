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
    public function createdDatetime(string $createdDatetime): static
    {
        $this->options['createdDatetime'] = $createdDatetime;

        return $this;
    }

    /**
     * @return $this
     */
    public function dataCoding(string $dataCoding): static
    {
        $this->options['dataCoding'] = $dataCoding;

        return $this;
    }

    /**
     * @return $this
     */
    public function gateway(int $gateway): static
    {
        $this->options['gateway'] = $gateway;

        return $this;
    }

    /**
     * @return $this
     */
    public function groupIds(array $groupIds): static
    {
        $this->options['groupIds'] = $groupIds;

        return $this;
    }

    /**
     * @return $this
     */
    public function mClass(int $mClass): static
    {
        $this->options['mClass'] = $mClass;

        return $this;
    }

    /**
     * @return $this
     */
    public function reference(string $reference): static
    {
        $this->options['reference'] = $reference;

        return $this;
    }

    /**
     * @return $this
     */
    public function reportUrl(string $reportUrl): static
    {
        $this->options['reportUrl'] = $reportUrl;

        return $this;
    }

    /**
     * @return $this
     */
    public function scheduledDatetime(string $scheduledDatetime): static
    {
        $this->options['scheduledDatetime'] = $scheduledDatetime;

        return $this;
    }

    /**
     * @return $this
     */
    public function shortenUrls(bool $shortenUrls): static
    {
        $this->options['shortenUrls'] = $shortenUrls;

        return $this;
    }

    /**
     * @return $this
     */
    public function type(string $type): static
    {
        $this->options['type'] = $type;

        return $this;
    }

    /**
     * @return $this
     */
    public function typeDetails(string $typeDetails): static
    {
        $this->options['typeDetails'] = $typeDetails;

        return $this;
    }

    /**
     * @return $this
     */
    public function validity(int $validity): static
    {
        $this->options['validity'] = $validity;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
