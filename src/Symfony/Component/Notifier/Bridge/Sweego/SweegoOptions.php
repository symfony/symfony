<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sweego;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

class SweegoOptions implements MessageOptionsInterface
{
    public const REGION = 'region';
    public const BAT = 'bat';
    public const CAMPAIGN_TYPE = 'campaign_type';
    public const CAMPAIGN_ID = 'campaign_id';
    public const SHORTEN_URLS = 'shorten_urls';
    public const SHORTEN_WITH_PROTOCOL = 'shorten_with_protocol';

    public function __construct(
        private array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    public function bat(bool $bat): self
    {
        $this->options[self::BAT] = $bat;

        return $this;
    }

    public function campaignId(string $campaignId): self
    {
        $this->options[self::CAMPAIGN_ID] = $campaignId;

        return $this;
    }

    public function shortenUrls(bool $shortenUrls): self
    {
        $this->options[self::SHORTEN_URLS] = $shortenUrls;

        return $this;
    }

    public function shortenWithProtocol(bool $shortenWithProtocol): self
    {
        $this->options[self::SHORTEN_WITH_PROTOCOL] = $shortenWithProtocol;

        return $this;
    }
}
