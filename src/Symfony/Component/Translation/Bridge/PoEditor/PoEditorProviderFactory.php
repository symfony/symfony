<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\PoEditor;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class PoEditorProviderFactory extends AbstractProviderFactory
{
    private const HOST = 'api.poeditor.com';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $defaultLocale,
        private readonly LoaderInterface $loader,
    ) {
    }

    public function create(Dsn $dsn): PoEditorProvider
    {
        if ('poeditor' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'poeditor', $this->getSupportedSchemes());
        }

        $endpoint = 'default' === $dsn->getHost() ? self::HOST : $dsn->getHost();
        $endpoint .= $dsn->getPort() ? ':'.$dsn->getPort() : '';

        $client = PoEditorHttpClient::create($this->client, 'https://'.$endpoint.'/v2/', $this->getPassword($dsn), $this->getUser($dsn));

        return new PoEditorProvider($client, $this->loader, $this->logger, $this->defaultLocale, $endpoint);
    }

    protected function getSupportedSchemes(): array
    {
        return ['poeditor'];
    }
}
