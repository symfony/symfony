<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Transport;

use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class SesTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $region = $dsn->getOption('region');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if ('ses+api' === $scheme) {
            if (!\extension_loaded('simplexml')) {
                throw new LogicException(sprintf('Cannot use "%s". Make sure you have "ext-simplexml" installed and enabled.', SesApiTransport::class));
            }

            return (new SesApiTransport($user, $password, $region, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('ses+https' === $scheme || 'ses' === $scheme) {
            return (new SesHttpTransport($user, $password, $region, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('ses+smtp' === $scheme || 'ses+smtps' === $scheme) {
            return new SesSmtpTransport($user, $password, $region, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'ses', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['ses', 'ses+api', 'ses+https', 'ses+smtp', 'ses+smtps'];
    }
}
