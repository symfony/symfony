<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translaton\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected const HOST = 'localhost';

    protected $client;
    protected $logger;

    protected $host;
    protected $port;

    public function __construct(HttpClientInterface $client = null, LoggerInterface $logger = null)
    {
        $this->client = $client;
        if (null === $client) {
            if (!class_exists(HttpClient::class)) {
                throw new LogicException(sprintf('You cannot use "%s" as the HttpClient component is not installed. Try running "composer require symfony/http-client".', __CLASS__));
            }

            $this->client = HttpClient::create();
        }

        $this->logger = $logger;
    }

    /**
     * @return $this
     */
    public function setHost(?string $host): self
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @return $this
     */
    public function setPort(?int $port): self
    {
        $this->port = $port;

        return $this;
    }

    protected function getEndpoint(): string
    {
        return ($this->host ?: $this->getDefaultHost()).($this->port ? ':'.$this->port : '');
    }

    protected function getDefaultHost(): string
    {
        return static::HOST;
    }
}
