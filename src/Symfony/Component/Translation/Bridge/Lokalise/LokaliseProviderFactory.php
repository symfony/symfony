<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Lokalise;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class LokaliseProviderFactory extends AbstractProviderFactory
{
    private const HOST = 'api.lokalise.com';

    private HttpClientInterface $client;
    private LoggerInterface $logger;
    private string $defaultLocale;
    private LoaderInterface $loader;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, string $defaultLocale, LoaderInterface $loader)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;
    }

    public function create(Dsn $dsn): LokaliseProvider
    {
        if ('lokalise' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'lokalise', $this->getSupportedSchemes());
        }

        $endpoint = 'default' === $dsn->getHost() ? self::HOST : $dsn->getHost();
        $endpoint .= $dsn->getPort() ? ':'.$dsn->getPort() : '';

        $client = $this->client->withOptions([
            'base_uri' => 'https://'.$endpoint.'/api2/projects/'.$this->getUser($dsn).'/',
            'headers' => [
                'X-Api-Token' => $this->getPassword($dsn),
            ],
        ]);

        return new LokaliseProvider($client, $this->loader, $this->logger, $this->defaultLocale, $endpoint);
    }

    protected function getSupportedSchemes(): array
    {
        return ['lokalise'];
    }
}
