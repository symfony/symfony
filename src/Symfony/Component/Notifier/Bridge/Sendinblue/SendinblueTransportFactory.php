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
 */
final class SendinblueTransportFactory extends AbstractTransportFactory
{
    /**
     * @return SendinblueTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('sendinblue' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sendinblue', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $sender = $dsn->getOption('sender');

        if (!$sender) {
            throw new IncompleteDsnException('Missing sender.', $dsn->getOriginalDsn());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SendinblueTransport($apiKey, $sender, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendinblue'];
    }
}
