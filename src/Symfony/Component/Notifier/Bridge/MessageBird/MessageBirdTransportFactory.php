<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageBird;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Vasilij Duško <vasilij@prado.lt>
 */
final class MessageBirdTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): MessageBirdTransport
    {
        $scheme = $dsn->getScheme();

        if ('messagebird' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'messagebird', $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $from = $dsn->getRequiredOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new MessageBirdTransport($token, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['messagebird'];
    }
}
