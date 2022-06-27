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

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Daniel GORGAN <https://github.com/danut007ro>
 */
final class EngagespotTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): EngagespotTransport
    {
        $scheme = $dsn->getScheme();

        if ('engagespot' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'engagespot', $this->getSupportedSchemes());
        }

        $apiKey = $dsn->getUser();
        $campaignName = $dsn->getRequiredOption('campaign_name');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new EngagespotTransport($apiKey, $campaignName, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['engagespot'];
    }
}
