<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sinch;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Iliya Miroslavov Iliev <i.miroslavov@gmail.com>
 */
final class SinchTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SinchTransport
    {
        $scheme = $dsn->getScheme();

        if ('sinch' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sinch', $this->getSupportedSchemes());
        }

        $accountSid = $this->getUser($dsn);
        $authToken = $this->getPassword($dsn);
        $from = $dsn->getRequiredOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SinchTransport($accountSid, $authToken, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sinch'];
    }
}
