<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\KazInfoTeh;

use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;

/**
 * @author Egor Taranov <dev@taranovegor.com>
 */
final class KazInfoTehTransportFactory extends AbstractTransportFactory
{
    /**
     * @inheritdoc
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('kazinfoteh' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'kazinfoteh', $this->getSupportedSchemes());
        }

        $username = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $sender = $dsn->getRequiredOption('sender');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new KazInfoTehTransport(
            $username,
            $password,
            $sender,
            $this->client,
            $this->dispatcher
        ))
            ->setHost($host)
            ->setPort($port)
        ;
    }

    /**
     * @inheritdoc
     */
    protected function getSupportedSchemes(): array
    {
        return ['kazinfoteh'];
    }
}
