<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twitter;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class TwitterTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TwitterTransport
    {
        $scheme = $dsn->getScheme();

        if ('twitter' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'twitter', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        [$apiSecret, $accessToken, $accessSecret] = explode(':', $this->getPassword($dsn)) + [1 => null, null, null];

        foreach (['API Key' => $apiKey, 'API Key Secret' => $apiSecret, 'Access Token' => $accessToken, 'Access Token Secret' => $accessSecret] as $name => $key) {
            if (!$key) {
                throw new IncompleteDsnException($name.' is missing.', 'twitter://'.$dsn->getHost());
            }
        }

        return (new TwitterTransport($apiKey, $apiSecret, $accessToken, $accessSecret, $this->client, $this->dispatcher))
            ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
            ->setPort($dsn->getPort());
    }

    protected function getSupportedSchemes(): array
    {
        return ['twitter'];
    }
}
