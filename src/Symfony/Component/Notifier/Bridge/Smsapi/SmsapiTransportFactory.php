<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsapi;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Marcin Szepczynski <szepczynski@gmail.com>
 */
final class SmsapiTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SmsapiTransport
    {
        $scheme = $dsn->getScheme();

        if ('smsapi' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'smsapi', $this->getSupportedSchemes());
        }

        $authToken = $this->getUser($dsn);
        $from = $dsn->getOption('from', '');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $fast = $dsn->getBooleanOption('fast');
        $test = $dsn->getBooleanOption('test');
        $port = $dsn->getPort();

        return (new SmsapiTransport($authToken, $from, $this->client, $this->dispatcher))->setFast($fast)->setHost($host)->setPort($port)->setTest($test);
    }

    protected function getSupportedSchemes(): array
    {
        return ['smsapi'];
    }
}
