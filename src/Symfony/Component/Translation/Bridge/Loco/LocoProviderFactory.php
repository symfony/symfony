<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Loco;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @experimental in 5.3
 */
final class LocoProviderFactory extends AbstractProviderFactory
{
    private $defaultLocale;
    private $loader;

    public function __construct(LoggerInterface $logger, string $defaultLocale, LoaderInterface $loader)
    {
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;
    }

    /**
     * @return LocoProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if ('loco' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'loco', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new LocoProvider($apiKey, $this->defaultLocale, $this->loader))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['loco'];
    }
}
