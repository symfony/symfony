<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ClickSend;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class ClickSendTransportFactory extends AbstractTransportFactory
{
    private const TRANSPORT_SCHEME = 'clicksend';

    public function create(Dsn $dsn): ClickSendTransport
    {
        $scheme = $dsn->getScheme();
        if (self::TRANSPORT_SCHEME !== $scheme) {
            throw new UnsupportedSchemeException($dsn, self::TRANSPORT_SCHEME, $this->getSupportedSchemes());
        }
        $apiUsername = $this->getUser($dsn);
        $apiKey = $this->getPassword($dsn);
        $from = $dsn->getOption('from');
        $source = $dsn->getOption('source');
        $listId = $dsn->getOption('list_id');
        $fromEmail = $dsn->getOption('from_email');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new ClickSendTransport($apiUsername, $apiKey, $from, $source, $listId, $fromEmail, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return [self::TRANSPORT_SCHEME];
    }
}
