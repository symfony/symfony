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
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @experimental in 5.3
 */
final class EsendexTransportFactory extends AbstractTransportFactory
{
    /**
     * @return EsendexTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $token = $this->getUser($dsn).':'.$this->getPassword($dsn);
        $accountReference = $dsn->getOption('accountreference');
        $from = $dsn->getOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if ('esendex' === $scheme) {
            return (new EsendexTransport($token, $accountReference, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'esendex', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['esendex'];
    }
}
