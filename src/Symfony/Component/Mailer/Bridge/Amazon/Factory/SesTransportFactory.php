<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Factory;

use Symfony\Component\Mailer\Bridge\Amazon;
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

        if ('api' === $scheme) {
            return new Amazon\Http\Api\SesTransport($user, $password, $region, $this->client, $this->dispatcher, $this->logger);
        }

        if ('http' === $scheme) {
            return new Amazon\Http\SesTransport($user, $password, $region, $this->client, $this->dispatcher, $this->logger);
        }

        if ('smtp' === $scheme) {
            return new Amazon\Smtp\SesTransport($user, $password, $region, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'ses' === $dsn->getHost();
    }
}
