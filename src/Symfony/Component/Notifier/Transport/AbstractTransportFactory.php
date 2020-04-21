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
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
abstract class AbstractTransportFactory implements TransportFactoryInterface
{
    protected $dispatcher;
    protected $client;

    public function __construct(EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null)
    {
        $this->dispatcher = class_exists(Event::class) ? LegacyEventDispatcherProxy::decorate($dispatcher) : $dispatcher;
        $this->client = $client;
    }

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->getScheme(), $this->getSupportedSchemes());
    }

    /**
     * @return string[]
     */
    abstract protected function getSupportedSchemes(): array;

    protected function getUser(Dsn $dsn): string
    {
        $user = $dsn->getUser();
        if (null === $user) {
            throw new IncompleteDsnException('User is not set.', $dsn->getOriginalDsn());
        }

        return $user;
    }

    protected function getPassword(Dsn $dsn): string
    {
        $password = $dsn->getPassword();
        if (null === $password) {
            throw new IncompleteDsnException('Password is not set.', $dsn->getOriginalDsn());
        }

        return $password;
    }
}
