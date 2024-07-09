<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bluesky;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class BlueskyTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        ?EventDispatcherInterface $dispatcher = null,
        ?HttpClientInterface $client = null,
        private ?LoggerInterface $logger = null,
        private readonly ?ClockInterface $clock = null,
    ) {
        parent::__construct($dispatcher, $client);
    }

    public function create(Dsn $dsn): BlueskyTransport
    {
        $scheme = $dsn->getScheme();

        if ('bluesky' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'bluesky', $this->getSupportedSchemes());
        }

        $user = $this->getUser($dsn);
        $secret = $this->getPassword($dsn);

        return (new BlueskyTransport($user, $secret, $this->logger ?? new NullLogger(), $this->client, $this->dispatcher, $this->clock))
            ->setHost($dsn->getHost())
            ->setPort($dsn->getPort());
    }

    protected function getSupportedSchemes(): array
    {
        return ['bluesky'];
    }
}
