<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class SendgridTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $key = $this->getUser($dsn);

        if ('sendgrid+api' === $dsn->getScheme()) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new SendgridApiTransport($key, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('sendgrid+smtp' === $dsn->getScheme() || 'sendgrid+smtps' === $dsn->getScheme() || 'sendgrid' === $dsn->getScheme()) {
            return new SendgridSmtpTransport($key, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'sendgrid', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendgrid', 'sendgrid+api', 'sendgrid+smtp', 'sendgrid+smtps'];
    }
}
