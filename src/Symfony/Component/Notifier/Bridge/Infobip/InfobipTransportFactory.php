<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Infobip;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
final class InfobipTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): InfobipTransport
    {
        $scheme = $dsn->getScheme();

        if ('infobip' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'infobip', $this->getSupportedSchemes());
        }

        $authToken = $this->getUser($dsn);
        $from = $dsn->getRequiredOption('from');
        $host = $dsn->getHost();
        $port = $dsn->getPort();

        return (new InfobipTransport($authToken, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['infobip'];
    }
}
