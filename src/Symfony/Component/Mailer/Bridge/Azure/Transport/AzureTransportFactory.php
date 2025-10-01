<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class AzureTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!\in_array($scheme, ['azure+api', 'azure'], true)) {
            throw new UnsupportedSchemeException($dsn, 'azure', $this->getSupportedSchemes());
        }

        $user = $this->getUser($dsn); // resourceName
        $password = $this->getPassword($dsn); // apiKey
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $apiVersion = $dsn->getOption('api_version', '2023-03-31');
        $disableTracking = (bool) $dsn->getOption('disable_tracking', false);

        return (new AzureApiTransport($password, $user, $disableTracking, $apiVersion, $this->client, $this->dispatcher, $this->logger))->setHost($host);
    }

    protected function getSupportedSchemes(): array
    {
        return ['azure', 'azure+api'];
    }
}
