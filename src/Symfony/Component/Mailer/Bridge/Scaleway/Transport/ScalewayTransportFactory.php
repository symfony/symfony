<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Scaleway\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class ScalewayTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $projectId = $this->getUser($dsn);
        $token = $this->getPassword($dsn);

        if ('scaleway+api' === $scheme || 'scaleway' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();
            $region = $dsn->getOption('region');

            return (new ScalewayApiTransport($projectId, $token, $region, $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
                ->setPort($port);
        }

        if ('scaleway+smtp' === $scheme || 'scaleway+smtps' === $scheme) {
            return new ScalewaySmtpTransport($projectId, $token, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'scaleway', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['scaleway', 'scaleway+api', 'scaleway+smtp', 'scaleway+smtps'];
    }
}
