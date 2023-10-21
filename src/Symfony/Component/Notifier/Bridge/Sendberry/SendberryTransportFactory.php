<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sendberry;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Vasilij Duško <vasilij@prado.lt>
 */
final class SendberryTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SendberryTransport
    {
        $scheme = $dsn->getScheme();

        if ('sendberry' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sendberry', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SendberryTransport($this->getUser($dsn), $this->getPassword($dsn), $dsn->getRequiredOption('auth_key'), $dsn->getRequiredOption('from'), $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendberry'];
    }
}
