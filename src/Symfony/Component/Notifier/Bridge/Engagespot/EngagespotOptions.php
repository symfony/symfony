<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Engagespot;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Daniel GORGAN <https://github.com/danut007ro>
 *
 * @see https://docs.engagespot.co/how-to-send-notifications-via-engagespot-api/how-to-send-notifications-using-engagespot-rest-api
 */
final class EngagespotOptions implements MessageOptionsInterface
{
    protected $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['to'];
    }

    /**
     * @return $this
     */
    public function campaignName(string $campaignName): static
    {
        $this->options['campaign_name'] = $campaignName;

        return $this;
    }

    /**
     * @return $this
     */
    public function to(string $to): static
    {
        $this->options['to'] = $to;

        return $this;
    }

    /**
     * @return $this
     */
    public function identifiers(array $identifiers): static
    {
        $this->options['identifiers'] = $identifiers;

        return $this;
    }

    /**
     * @return $this
     */
    public function everyone(bool $everyone): static
    {
        $this->options['everyone'] = $everyone;

        return $this;
    }

    /**
     * @return $this
     */
    public function icon(string $icon): static
    {
        $this->options['icon'] = $icon;

        return $this;
    }

    /**
     * @return $this
     */
    public function url(string $url): static
    {
        $this->options['url'] = $url;

        return $this;
    }
}
