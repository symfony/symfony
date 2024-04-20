<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

final class EsendexTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): EsendexTransport
    {
        $scheme = $dsn->getScheme();

        if ('esendex' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'esendex', $this->getSupportedSchemes());
        }

        $email = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $accountReference = $dsn->getRequiredOption('accountreference');
        $from = $dsn->getRequiredOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new EsendexTransport($email, $password, $accountReference, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['esendex'];
    }
}
