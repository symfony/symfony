<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailomat\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class MailomatTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $schema = $dsn->getScheme();

        if ('mailomat+api' === $schema) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new MailomatApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
                ->setPort($port)
            ;
        }

        if (\in_array($schema, ['mailomat+smtp', 'mailomat+smtps', 'mailomat'], true)) {
            return new MailomatSmtpTransport($dsn->getUser(), $dsn->getPassword(), $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'mailomat', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mailomat', 'mailomat+api', 'mailomat+smtp', 'mailomat+smtps'];
    }
}
