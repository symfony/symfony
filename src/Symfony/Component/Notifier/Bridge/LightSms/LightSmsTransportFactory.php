<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LightSms;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Vasilij Duško <vasilij@prado.lt>
 */
final class LightSmsTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): LightSmsTransport
    {
        $scheme = $dsn->getScheme();

        if ('lightsms' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'lightsms', $this->getSupportedSchemes());
        }

        $login = $this->getUser($dsn);
        $token = $this->getPassword($dsn);
        $from = $dsn->getRequiredOption('from');

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new LightSmsTransport($login, $token, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['lightsms'];
    }
}
