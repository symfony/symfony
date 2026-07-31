<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\TurboSmtp\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Dominik Spitzli <dominik@spitzli.dev>
 */
final class TurboSmtpTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        $password = $this->getPassword($dsn);

        if ('turbosmtp+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();

            return (new TurboSmtpApiTransport($user, $password, $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
                ->setPort($dsn->getPort());
        }

        if ('turbosmtp+smtp' === $scheme || 'turbosmtp' === $scheme) {
            $host = 'default' === $dsn->getHost() ? 'pro.turbo-smtp.com' : $dsn->getHost();

            return new TurboSmtpSmtpTransport($host, $user, $password, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'turbosmtp', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['turbosmtp', 'turbosmtp+api', 'turbosmtp+smtp'];
    }
}
