<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Provider;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractProvider implements ProviderInterface
{
    protected const HOST = 'localhost';

    protected $client;
    protected $host;
    protected $port;

    public function __construct(HttpClientInterface $client = null)
    {
        $this->client = $client;

        if (null === $client) {
            if (!class_exists(HttpClient::class)) {
                throw new LogicException(sprintf('You cannot use "%s" as the HttpClient component is not installed. Try running "composer require symfony/http-client".', __CLASS__));
            }

            $this->client = HttpClient::create();
        }
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

    protected function getEndpoint(): ?string
    {
        return ($this->host ?: $this->getDefaultHost()).($this->port ? ':'.$this->port : '');
    }

    protected function getDefaultHost(): string
    {
        return static::HOST;
    }

    protected function getDefaultHeaders(): array
    {
        return [];
    }
}
