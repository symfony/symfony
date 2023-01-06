<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mastodon;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Quentin Dequippe <quentin@dequippe.tech>
 */
final class MastodonTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): MastodonTransport
    {
        $scheme = $dsn->getScheme();

        if ('mastodon' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'mastodon', $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $host = $dsn->getHost();
        $port = $dsn->getPort();

        return (new MastodonTransport($token, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['mastodon'];
    }
}
