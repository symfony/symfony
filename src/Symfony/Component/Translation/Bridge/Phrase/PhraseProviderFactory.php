<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Phrase;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author wicliff <wicliff.wolda@gmail.com>
 */
class PhraseProviderFactory extends AbstractProviderFactory
{
    private const HOST = 'api.phrase.com';
    private const READ_CONFIG_DEFAULT = [
        'file_format' => 'symfony_xliff',
        'include_empty_translations' => '1',
        'tags' => [],
        'format_options' => [
            'enclose_in_cdata' => '1',
        ],
    ];
    private const WRITE_CONFIG_DEFAULT = [
        'file_format' => 'symfony_xliff',
        'update_translations' => '1',
    ];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly LoaderInterface $loader,
        private readonly XliffFileDumper $xliffFileDumper,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $defaultLocale,
    ) {
    }

    public function create(Dsn $dsn): ProviderInterface
    {
        if ('phrase' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'phrase', $this->getSupportedSchemes());
        }

        $endpoint = 'default' === $dsn->getHost() ? self::HOST : $dsn->getHost();

        if (null !== $port = $dsn->getPort()) {
            $endpoint .= ':'.$port;
        }

        $client = $this->client->withOptions([
            'base_uri' => 'https://'.$endpoint.'/v2/projects/'.$this->getUser($dsn).'/',
            'headers' => [
                'Authorization' => 'token '.$this->getPassword($dsn),
                'User-Agent' => $dsn->getRequiredOption('userAgent'),
            ],
        ]);

        $readConfig = $this->readConfigFromDsn($dsn);
        $writeConfig = $this->writeConfigFromDsn($dsn);

        return new PhraseProvider($client, $this->logger, $this->loader, $this->xliffFileDumper, $this->cache, $this->defaultLocale, $endpoint, $readConfig, $writeConfig, $this->isFallbackLocaleEnabled($dsn));
    }

    protected function getSupportedSchemes(): array
    {
        return ['phrase'];
    }

    private function isFallbackLocaleEnabled(Dsn $dsn): bool
    {
        $options = $dsn->getOptions()['read'] ?? [];

        return filter_var($options['fallback_locale_enabled'] ?? false, \FILTER_VALIDATE_BOOL);
    }

    private function readConfigFromDsn(Dsn $dsn): array
    {
        $options = $dsn->getOptions()['read'] ?? [];

        // enforce empty translations when fallback locale is enabled
        if ($this->isFallbackLocaleEnabled($dsn)) {
            $options['include_empty_translations'] = '1';
        }

        unset($options['file_format'], $options['tags'], $options['tag'], $options['fallback_locale_id'], $options['fallback_locale_enabled']);

        $options['format_options'] = array_merge(self::READ_CONFIG_DEFAULT['format_options'], $options['format_options'] ?? []);

        return array_merge(self::READ_CONFIG_DEFAULT, $options);
    }

    private function writeConfigFromDsn(Dsn $dsn): array
    {
        $options = $dsn->getOptions()['write'] ?? [];

        unset($options['file_format'], $options['tags'], $options['locale_id'], $options['file']);

        return array_merge(self::WRITE_CONFIG_DEFAULT, $options);
    }
}
