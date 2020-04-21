<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Transport;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
abstract class AbstractTransport implements TransportInterface
{
    protected const HOST = 'localhost';

    private $dispatcher;

    protected $client;
    protected $host;
    protected $port;

    public function __construct(HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->client = $client;
        if (null === $client) {
            if (!class_exists(HttpClient::class)) {
                throw new LogicException(sprintf('You cannot use "%s" as the HttpClient component is not installed. Try running "composer require symfony/http-client".', __CLASS__));
            }

            $this->client = HttpClient::create();
        }

        $this->dispatcher = class_exists(Event::class) ? LegacyEventDispatcherProxy::decorate($dispatcher) : $dispatcher;
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

    public function send(MessageInterface $message): void
    {
        if (null !== $this->dispatcher) {
            $this->dispatcher->dispatch(new MessageEvent($message));
        }

        $this->doSend($message);
    }

    abstract protected function doSend(MessageInterface $message): void;

    protected function getEndpoint(): ?string
    {
        return ($this->host ?: $this->getDefaultHost()).($this->port ? ':'.$this->port : '');
    }

    protected function getDefaultHost(): string
    {
        return static::HOST;
    }
}
