<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailtrap\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MailtrapTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);

        if ('mailtrap+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new MailtrapApiTransport($user, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('mailtrap+smtp' === $scheme || 'mailtrap+smtps' === $scheme || 'mailtrap' === $scheme) {
            return new MailtrapSmtpTransport($user, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'mailtrap', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mailtrap', 'mailtrap+api', 'mailtrap+smtp', 'mailtrap+smtps'];
    }
}
