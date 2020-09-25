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
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PoEditorProviderFactory extends AbstractProviderFactory
{
    /** @var LoaderInterface */
    private $loader;

    public function __construct(HttpClientInterface $client = null, LoggerInterface $logger = null, string $defaultLocale = null, LoaderInterface $loader = null)
    {
        parent::__construct($client, $logger, $defaultLocale);

        $this->loader = $loader;
    }

    /**
     * @return PhraseProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if ('poeditor' === $dsn->getScheme()) {
            return (new PoEditorProvider($this->getUser($dsn), $this->getPassword($dsn), $this->client, $this->loader, $this->logger, $this->defaultLocale))
                ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                ->setPort($dsn->getPort())
            ;
        }

        throw new UnsupportedSchemeException($dsn, 'poeditor', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['poeditor'];
    }
}
