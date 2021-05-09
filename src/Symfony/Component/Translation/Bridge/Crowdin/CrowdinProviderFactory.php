<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Crowdin;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Andrii Bodnar <andrii.bodnar@crowdin.com>
 *
 * @experimental in 5.3
 */
final class CrowdinProviderFactory extends AbstractProviderFactory
{
    private const HOST = 'api.crowdin.com/api/v2/';
    private const DSN_OPTION_DOMAIN = 'domain';

    /** @var LoaderInterface */
    private $loader;

    /** @var HttpClientInterface */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $defaultLocale;

    /** @var XliffFileDumper */
    private $xliffFileDumper;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, string $defaultLocale, LoaderInterface $loader, XliffFileDumper $xliffFileDumper)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;
        $this->xliffFileDumper = $xliffFileDumper;
    }

    /**
     * @return CrowdinProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if ('crowdin' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'crowdin', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? $this->getHost($dsn) : $dsn->getHost();
        $endpoint = sprintf('%s%s', $host, $dsn->getPort() ? ':'.$dsn->getPort() : '');

        $filesDownloader = $this->client;

        $client = $this->client->withOptions([
            'base_uri' => 'https://'.$endpoint,
            'headers' => [
                'Authorization' => 'Bearer '.$this->getPassword($dsn),
            ],
        ]);

        return new CrowdinProvider($client, $this->loader, $this->logger, $this->xliffFileDumper, $this->defaultLocale, $endpoint, (int) $this->getUser($dsn), $filesDownloader);
    }

    protected function getSupportedSchemes(): array
    {
        return ['crowdin'];
    }

    protected function getHost(Dsn $dsn): string
    {
        $organizationDomain = $dsn->getOption(self::DSN_OPTION_DOMAIN);

        if ($organizationDomain) {
            return sprintf('%s.%s', $organizationDomain, self::HOST);
        } else {
            return self::HOST;
        }
    }
}
