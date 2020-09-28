<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Transifex;

use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TransifexProviderFactory extends AbstractProviderFactory
{
    /** @var LoaderInterface */
    private $loader;

    /** @var AsciiSlugger */
    private $slugger;

    public function __construct(HttpClientInterface $client = null, LoggerInterface $logger = null, string $defaultLocale = null, LoaderInterface $loader = null, AsciiSlugger $slugger = null)
    {
        parent::__construct($client, $logger, $defaultLocale);

        $this->loader = $loader;
        $this->slugger = $slugger;
    }

    /**
     * @return TransifexProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if ('transifex' === $dsn->getScheme()) {
            return (new TransifexProvider($this->getUser($dsn), $this->getPassword($dsn), $this->client, $this->loader, $this->logger, $this->defaultLocale, $this->slugger))
                ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                ->setPort($dsn->getPort())
            ;
        }

        throw new UnsupportedSchemeException($dsn, 'transifex', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['transifex'];
    }
}
