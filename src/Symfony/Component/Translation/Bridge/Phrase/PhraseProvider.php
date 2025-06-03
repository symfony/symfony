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
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author wicliff <wicliff.wolda@gmail.com>
 */
class PhraseProvider implements ProviderInterface
{
    private array $phraseLocales = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly LoaderInterface $loader,
        private readonly XliffFileDumper $xliffFileDumper,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $defaultLocale,
        private readonly string $endpoint,
        private array $readConfig,
        private array $writeConfig,
        private readonly bool $isFallbackLocaleEnabled = false,
    ) {
    }

    public function __toString(): string
    {
        return \sprintf('phrase://%s', $this->endpoint);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        \assert($translatorBag instanceof TranslatorBag);

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            foreach ($catalogue->getDomains() as $domain) {
                if (!\count($catalogue->all($domain))) {
                    continue;
                }

                $phraseLocale = $this->getLocale($catalogue->getLocale());

                $content = $this->xliffFileDumper->formatCatalogue($catalogue, $domain, ['default_locale' => $this->defaultLocale]);
                $filename = \sprintf('%d-%s-%s.xlf', date('YmdHis'), $domain, $catalogue->getLocale());

                $this->writeConfig['tags'] = $domain;
                $this->writeConfig['locale_id'] = $phraseLocale;
                $fields = array_merge($this->writeConfig, ['file' => new DataPart($content, $filename, 'application/xml')]);

                $formData = new FormDataPart($fields);

                $response = $this->client->request('POST', 'uploads', [
                    'body' => $formData->bodyToIterable(),
                    'headers' => $formData->getPreparedHeaders()->toArray(),
                ]);

                if (201 !== $statusCode = $response->getStatusCode()) {
                    $this->logger->error(\sprintf('Unable to upload translations for domain "%s" to phrase: "%s".', $domain, $response->getContent(false)));

                    $this->throwProviderException($statusCode, $response, 'Unable to upload translations to phrase.');
                }
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            $phraseLocale = $this->getLocale($locale);

            foreach ($domains as $domain) {
                $this->readConfig['tags'] = $domain;

                if ($this->isFallbackLocaleEnabled && null !== $fallbackLocale = $this->getFallbackLocale($locale)) {
                    $this->readConfig['fallback_locale_id'] = $fallbackLocale;
                }

                $cacheKey = $this->generateCacheKey($locale, $domain, $this->readConfig);
                $cacheItem = $this->cache->getItem($cacheKey);

                $headers = [];
                $cachedResponse = null;

                if ($cacheItem->isHit() && null !== $cachedResponse = $cacheItem->get()) {
                    $headers = ['If-None-Match' => $cachedResponse['etag']];
                }

                $response = $this->client->request('GET', 'locales/'.$phraseLocale.'/download', [
                    'query' => $this->readConfig,
                    'headers' => $headers,
                ]);

                if (200 !== ($statusCode = $response->getStatusCode()) && 304 !== $statusCode) {
                    $this->logger->error(\sprintf('Unable to get translations for locale "%s" from phrase: "%s".', $locale, $response->getContent(false)));

                    $this->throwProviderException($statusCode, $response, 'Unable to get translations from phrase.');
                }

                $content = 304 === $statusCode && null !== $cachedResponse ? $cachedResponse['content'] : $response->getContent();
                $translatorBag->addCatalogue($this->loader->load($content, $locale, $domain));

                // using weak etags, responses for requests with fallback locale enabled can not be reliably cached...
                if (!$this->isFallbackLocaleEnabled) {
                    $headers = $response->getHeaders(false);
                    $cacheItem->set(['etag' => $headers['etag'][0], 'modified' => $headers['last-modified'][0], 'content' => $content]);
                    $this->cache->save($cacheItem);
                }
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        $keys = [[]];

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            foreach ($catalogue->getDomains() as $domain) {
                $keys[] = array_keys($catalogue->all($domain));
            }
        }

        $keys = array_unique(array_merge(...$keys));
        $names = array_map(static fn ($v): ?string => preg_replace('/([\s:,])/', '\\\\\\\\$1', $v), $keys);

        foreach ($names as $name) {
            $response = $this->client->request('DELETE', 'keys', [
                'query' => [
                    'q' => 'name:'.$name,
                ],
            ]);

            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(\sprintf('Unable to delete key "%s" in phrase: "%s".', $name, $response->getContent(false)));

                $this->throwProviderException($statusCode, $response, 'Unable to delete key in phrase.');
            }
        }
    }

    private function generateCacheKey(string $locale, string $domain, array $options): string
    {
        array_multisort($options);

        return \sprintf('%s.%s.%s', $locale, $domain, sha1(serialize($options)));
    }

    private function getLocale(string $locale): string
    {
        if (!$this->phraseLocales) {
            $this->initLocales();
        }

        $phraseCode = str_replace('_', '-', $locale);

        if (!\array_key_exists($phraseCode, $this->phraseLocales)) {
            $this->createLocale($phraseCode);
        }

        return $this->phraseLocales[$phraseCode]['id'];
    }

    private function getFallbackLocale(string $locale): ?string
    {
        $phraseLocale = str_replace('_', '-', $locale);

        return $this->phraseLocales[$phraseLocale]['fallback_locale']['name'] ?? null;
    }

    private function createLocale(string $locale): void
    {
        $response = $this->client->request('POST', 'locales', [
            'body' => [
                'name' => $locale,
                'code' => $locale,
                'default' => $locale === str_replace('_', '-', $this->defaultLocale),
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        if (201 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(\sprintf('Unable to create locale "%s" in phrase: "%s".', $locale, $response->getContent(false)));

            $this->throwProviderException($statusCode, $response, 'Unable to create locale phrase.');
        }

        $phraseLocale = $response->toArray();

        $this->phraseLocales[$phraseLocale['name']] = $phraseLocale;
    }

    private function initLocales(): void
    {
        $page = 1;

        do {
            $response = $this->client->request('GET', 'locales', [
                'query' => [
                    'per_page' => 100,
                    'page' => $page,
                ],
            ]);

            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(\sprintf('Unable to get locales from phrase: "%s".', $response->getContent(false)));

                $this->throwProviderException($statusCode, $response, 'Unable to get locales from phrase.');
            }

            foreach ($response->toArray() as $phraseLocale) {
                $this->phraseLocales[$phraseLocale['name']] = $phraseLocale;
            }

            $pagination = $response->getHeaders()['pagination'][0] ?? '{}';
            $page = json_decode($pagination, true)['next_page'] ?? null;
        } while (null !== $page);
    }

    private function throwProviderException(int $statusCode, ResponseInterface $response, string $message): void
    {
        $headers = $response->getHeaders(false);

        throw match (true) {
            429 === $statusCode => new ProviderException(\sprintf('Rate limit exceeded (%s). please wait %s seconds.',
                $headers['x-rate-limit-limit'][0],
                $headers['x-rate-limit-reset'][0]
            ), $response),
            $statusCode <= 500 => new ProviderException($message, $response),
            default => new ProviderException('Provider server error.', $response),
        };
    }
}
