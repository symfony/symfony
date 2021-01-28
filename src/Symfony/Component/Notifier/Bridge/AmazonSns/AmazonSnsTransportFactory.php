<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AmazonSns;

use AsyncAws\Sns\SnsClient;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Adrien Chinour <github@chinour.fr>
 */
final class AmazonSnsTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('sns' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sns', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        $options = [
                'region' => $dsn->getOption('region') ?: 'eu-west-1',
                'profile' => $dsn->getOption('profile'),
                'accessKeyId' => $dsn->getUser(),
                'accessKeySecret' => $dsn->getPassword(),
            ] + (
            null === $host ? [] : ['endpoint' => 'https://'.$host.($port ? ':'.$port : '')]
            );

        return (new AmazonSnsTransport(new SnsClient($options, null, $this->client), $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sns'];
    }
}
