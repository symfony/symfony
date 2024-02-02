<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsense;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Valentin Barbu <jimiero@gmail.com>
 */
final class SmsenseTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SmsenseTransport
    {
        if ('smsense' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'smsense', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $from = $dsn->getRequiredOption('from');
        $authToken = $this->getUser($dsn);
        $port = $dsn->getPort();

        return (new SmsenseTransport($authToken, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['smsense'];
    }
}
