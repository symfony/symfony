<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class MailgunTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $region = $dsn->getOption('region');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if ('mailgun+api' === $scheme) {
            return (new MailgunApiTransport($user, $password, $region, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('mailgun+https' === $scheme || 'mailgun' === $scheme) {
            return (new MailgunHttpTransport($user, $password, $region, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('mailgun+smtp' === $scheme || 'mailgun+smtps' === $scheme) {
            return new MailgunSmtpTransport($user, $password, $region, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'mailgun', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mailgun', 'mailgun+api', 'mailgun+https', 'mailgun+smtp', 'mailgun+smtps'];
    }
}
