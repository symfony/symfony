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

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Pierre Tondereau <pierre.tondereau@protonmail.com>
 */
final class SendinblueTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SendinblueTransport
    {
        $scheme = $dsn->getScheme();

        if ('sendinblue' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sendinblue', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $sender = $dsn->getRequiredOption('sender');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SendinblueTransport($apiKey, $sender, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendinblue'];
    }
}
