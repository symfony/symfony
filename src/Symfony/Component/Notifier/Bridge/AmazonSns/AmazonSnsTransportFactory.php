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

/**
 * @author Adrien Chinour <github@chinour.fr>
 */
final class AmazonSnsTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): AmazonSnsTransport
    {
        $scheme = $dsn->getScheme();

        if ('sns' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sns', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        $options = null === $host ? [] : ['endpoint' => 'https://'.$host.($port ? ':'.$port : '')];

        if ($dsn->getUser()) {
            $options += [
                'accessKeyId' => $dsn->getUser(),
                'accessKeySecret' => $dsn->getPassword(),
            ];
        }

        if ($dsn->getOption('region')) {
            $options['region'] = $dsn->getOption('region');
        }

        if ($dsn->getOption('profile')) {
            $options['profile'] = $dsn->getOption('profile');
        }

        return (new AmazonSnsTransport(new SnsClient($options, null, $this->client), $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sns'];
    }
}
