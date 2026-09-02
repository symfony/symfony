<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Instagram;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class InstagramTransportFactory extends AbstractTransportFactory
{
    private const DEFAULT_API_VERSION = 'v22.0';

    public function create(Dsn $dsn): InstagramTransport
    {
        $scheme = $dsn->getScheme();

        if ('instagram' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'instagram', $this->getSupportedSchemes());
        }

        $accessToken = $this->getUser($dsn);
        $userId = $dsn->getRequiredOption('user_id');
        $apiVersion = $dsn->getOption('api_version', self::DEFAULT_API_VERSION);
        $pollAttempts = (int) $dsn->getOption('poll_attempts', InstagramTransport::POLL_ATTEMPTS);
        $pollDelay = (float) $dsn->getOption('poll_delay', InstagramTransport::POLL_DELAY_SECONDS);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new InstagramTransport($accessToken, $userId, $apiVersion, $this->client, $this->dispatcher, $pollAttempts, $pollDelay))->setHost($host)->setPort($port)->setSsl($this->getSsl($dsn));
    }

    protected function getSupportedSchemes(): array
    {
        return ['instagram'];
    }
}
