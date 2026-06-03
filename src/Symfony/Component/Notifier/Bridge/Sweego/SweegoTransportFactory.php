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

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class SweegoTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SweegoTransport
    {
        $scheme = $dsn->getScheme();

        if ('sweego' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sweego', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $region = $dsn->getRequiredOption(SweegoOptions::REGION);
        $campaignType = $dsn->getRequiredOption(SweegoOptions::CAMPAIGN_TYPE);
        $bat = $dsn->getBooleanOption(SweegoOptions::BAT, false);
        $campaignId = $dsn->getOption(SweegoOptions::CAMPAIGN_ID);
        $shortenUrls = $dsn->getBooleanOption(SweegoOptions::SHORTEN_URLS, true);
        $shortenWithProtocol = $dsn->getBooleanOption(SweegoOptions::SHORTEN_WITH_PROTOCOL, true);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SweegoTransport($apiKey, $region, $campaignType, $bat, $campaignId, $shortenUrls, $shortenWithProtocol, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sweego'];
    }
}
