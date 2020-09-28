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
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 */
final class CrowdinProviderFactory extends AbstractProviderFactory
{
    /** @var LoaderInterface */
    private $loader;

    /** @var XliffFileDumper */
    private $xliffFileDumper;

    public function __construct(HttpClientInterface $client = null, LoggerInterface $logger = null, string $defaultLocale = null, LoaderInterface $loader = null, XliffFileDumper $xliffFileDumper = null)
    {
        parent::__construct($client, $logger, $defaultLocale);

        $this->loader = $loader;
        $this->xliffFileDumper = $xliffFileDumper;
    }

    /**
     * @return CrowdinProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if ('crowdin' === $dsn->getScheme()) {
            return (new CrowdinProvider($this->getUser($dsn), $this->getPassword($dsn), $this->client, $this->loader, $this->logger, $this->defaultLocale, $this->xliffFileDumper))
                ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                ->setPort($dsn->getPort())
            ;
        }

        throw new UnsupportedSchemeException($dsn, 'crowdin', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['crowdin'];
    }
}
