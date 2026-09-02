<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Threads;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ThreadsTransportFactory extends AbstractTransportFactory
{
    private const DEFAULT_API_VERSION = 'v1.0';

    public function create(Dsn $dsn): ThreadsTransport
    {
        $scheme = $dsn->getScheme();

        if ('threads' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'threads', $this->getSupportedSchemes());
        }

        $accessToken = $this->getUser($dsn);
        $userId = $dsn->getRequiredOption('user_id');
        $apiVersion = $dsn->getOption('api_version', self::DEFAULT_API_VERSION);
        $pollAttempts = (int) $dsn->getOption('poll_attempts', ThreadsTransport::POLL_ATTEMPTS);
        $pollDelay = (float) $dsn->getOption('poll_delay', ThreadsTransport::POLL_DELAY_SECONDS);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new ThreadsTransport($accessToken, $userId, $apiVersion, $this->client, $this->dispatcher, $pollAttempts, $pollDelay))->setHost($host)->setPort($port)->setSsl($this->getSsl($dsn));
    }

    protected function getSupportedSchemes(): array
    {
        return ['threads'];
    }
}
