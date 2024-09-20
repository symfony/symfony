<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Infobip\Transport;

use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class InfobipTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $schema = $dsn->getScheme();
        $apiKey = $this->getUser($dsn);

        if ('infobip+api' === $schema) {
            $host = $dsn->getHost();
            if ('default' === $host) {
                throw new IncompleteDsnException('Infobip mailer for API DSN must contain a host.');
            }

            return (new InfobipApiTransport($apiKey, $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
            ;
        }

        if (\in_array($schema, ['infobip+smtp', 'infobip+smtps', 'infobip'], true)) {
            return new InfobipSmtpTransport($apiKey, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'infobip', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['infobip', 'infobip+api', 'infobip+smtp', 'infobip+smtps'];
    }
}
