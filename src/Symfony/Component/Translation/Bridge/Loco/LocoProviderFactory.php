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
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class LocoProviderFactory extends AbstractProviderFactory
{
    private const HOST = 'localise.biz';

    private HttpClientInterface $client;
    private LoggerInterface $logger;
    private string $defaultLocale;
    private LoaderInterface $loader;
    private ?TranslatorBagInterface $translatorBag = null;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, string $defaultLocale, LoaderInterface $loader, TranslatorBagInterface $translatorBag = null)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;
        $this->translatorBag = $translatorBag;
    }

    public function create(Dsn $dsn): LocoProvider
    {
        if ('loco' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'loco', $this->getSupportedSchemes());
        }

        $endpoint = 'default' === $dsn->getHost() ? self::HOST : $dsn->getHost();
        $endpoint .= $dsn->getPort() ? ':'.$dsn->getPort() : '';

        $client = $this->client->withOptions([
            'base_uri' => 'https://'.$endpoint.'/api/',
            'headers' => [
                'Authorization' => 'Loco '.$this->getUser($dsn),
            ],
        ]);

        return new LocoProvider($client, $this->loader, $this->logger, $this->defaultLocale, $endpoint, $this->translatorBag);
    }

    protected function getSupportedSchemes(): array
    {
        return ['loco'];
    }
}
