<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telnyx;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Vasilij Duško <vasilij@prado.lt>
 */
final class TelnyxTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TelnyxTransport
    {
        $scheme = $dsn->getScheme();

        if ('telnyx' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'telnyx', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $from = $dsn->getRequiredOption('from');
        $messagingProfileId = $dsn->getOption('messaging_profile_id');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new TelnyxTransport($apiKey, $from, $messagingProfileId, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['telnyx'];
    }
}
