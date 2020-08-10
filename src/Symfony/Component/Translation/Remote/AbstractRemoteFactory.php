<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Remote;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractRemoteFactory implements RemoteFactoryInterface
{
    /** @var HttpClientInterface|null */
    protected $client;

    /** @var LoaderInterface|null */
    protected $loader;

    /** @var LoggerInterface|null */
    protected $logger;

    /** @var string|null */
    protected $defaultLocale;

    public function __construct(HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
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
