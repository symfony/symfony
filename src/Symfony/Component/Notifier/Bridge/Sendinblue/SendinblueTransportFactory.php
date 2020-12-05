<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sendinblue;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Pierre Tondereau <pierre.tondereau@protonmail.com>
 *
 * @experimental in 5.3
 */
final class SendinblueTransportFactory extends AbstractTransportFactory
{
    /**
     * @return SendinblueTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        if (!$sender = $dsn->getOption('sender')) {
            throw new IncompleteDsnException('Missing sender.', $dsn->getOriginalDsn());
        }

        $scheme = $dsn->getScheme();
        $apiKey = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if ('sendinblue' === $scheme) {
            return (new SendinblueTransport($apiKey, $sender, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'sendinblue', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendinblue'];
    }
}
