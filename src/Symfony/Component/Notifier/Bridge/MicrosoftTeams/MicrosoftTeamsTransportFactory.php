<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MicrosoftTeamsTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): MicrosoftTeamsTransport
    {
        $scheme = $dsn->getScheme();

        if ('microsoftteams' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'microsoftteams', $this->getSupportedSchemes());
        }

        $path = $dsn->getPath();

        if (null === $path) {
            throw new IncompleteDsnException('Path is not set.', 'microsoftteams://'.$dsn->getHost());
        }

        $host = $dsn->getHost();
        $port = $dsn->getPort();

        return (new MicrosoftTeamsTransport($path, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['microsoftteams'];
    }
}
