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
use Symfony\Component\Translation\CatalogueMetadataAwareInterface;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * In Loco:
 *  * Tags refers to Symfony's translation domains
 *  * Assets refers to Symfony's translation keys
 *  * Translations refers to Symfony's translated messages
 */
final class LocoProvider implements ProviderInterface
{
    private HttpClientInterface $client;
    private LoaderInterface $loader;
    private LoggerInterface $logger;
    private string $defaultLocale;
    private string $endpoint;
    private ?TranslatorBagInterface $translatorBag = null;

    public function __construct(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint, TranslatorBagInterface $translatorBag = null)
    {
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->endpoint = $endpoint;
        $this->translatorBag = $translatorBag;
    }

    public function __toString(): string
    {
        return sprintf('loco://%s', $this->endpoint);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $catalogue = $translatorBag->getCatalogue($this->defaultLocale);

        if (!$catalogue) {
            $catalogue = $translatorBag->getCatalogues()[0];
        }

        foreach ($catalogue->all() as $domain => $messages) {
            $createdIds = $this->createAssets(array_keys($messages), $domain);
            if ($createdIds) {
                $this->tagsAssets($createdIds, $domain);
            }
        }

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locale = $catalogue->getLocale();

            if (!\in_array($locale, $this->getLocales())) {
                $this->createLocale($locale);
            }

            foreach ($catalogue->all() as $domain => $messages) {
                $keysIdsMap = [];

                foreach ($this->getAssetsIds($domain) as $id) {
                    $keysIdsMap[$this->retrieveKeyFromId($id, $domain)] = $id;
                }

                $assets = [];
                foreach ($keysIdsMap as $key => $id) {
                    if (isset($messages[$key])) {
                        $assets[$id] = $messages[$key];
                    }
                }

                $this->translateAssets($assets, $locale);
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $domains = $domains ?: ['*'];
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $previousCatalogue = $this->translatorBag?->getCatalogue($locale);

                // Loco forbids concurrent requests, so the requests must be synchronous in order to prevent "429 Too Many Requests" errors.
                $response = $this->client->request('GET', sprintf('export/locale/%s.xlf', rawurlencode($locale)), [
                    'query' => [
                        'filter' => $domain,
                        'status' => 'translated,blank-translation',
                    ],
                    'headers' => [
                        'If-Modified-Since' => $previousCatalogue instanceof CatalogueMetadataAwareInterface ? $previousCatalogue->getCatalogueMetadata('last-modified', $domain) : null,
                    ],
                ]);

                if (404 === $response->getStatusCode()) {
                    $this->logger->warning(sprintf('Locale "%s" for domain "%s" does not exist in Loco.', $locale, $domain));
                    continue;
                }

                if (304 === $response->getStatusCode()) {
                    $this->logger->info(sprintf('No modifications found for locale "%s" and domain "%s" in Loco.', $locale, $domain));

                    $catalogue = new MessageCatalogue($locale);
                    $previousMessages = $previousCatalogue->all($domain);

                    if (!str_ends_with($domain, $catalogue::INTL_DOMAIN_SUFFIX)) {
                        $previousMessages = array_diff_key($previousMessages, $previousCatalogue->all($domain.$catalogue::INTL_DOMAIN_SUFFIX));
                    }
                    foreach ($previousMessages as $key => $message) {
                        $catalogue->set($this->retrieveKeyFromId($key, $domain), $message, $domain);
                    }

                    foreach ($previousCatalogue->getCatalogueMetadata('', $domain) as $key => $value) {
                        $catalogue->setCatalogueMetadata($key, $value, $domain);
                    }

                    $translatorBag->addCatalogue($catalogue);

                    continue;
                }

                $responseContent = $response->getContent(false);

                if (200 !== $response->getStatusCode()) {
                    throw new ProviderException('Unable to read the Loco response: '.$responseContent, $response);
                }

                $locoCatalogue = $this->loader->load($responseContent, $locale, $domain);
                $catalogue = new MessageCatalogue($locale);

                foreach ($locoCatalogue->all($domain) as $key => $message) {
                    $catalogue->set($this->retrieveKeyFromId($key, $domain), $message, $domain);
                }

                if ($previousCatalogue instanceof CatalogueMetadataAwareInterface) {
                    foreach ($previousCatalogue->getCatalogueMetadata('', $domain) ?? [] as $key => $value) {
                        $catalogue->setCatalogueMetadata($key, $value, $domain);
                    }
                }

                if (null !== $lastModified = $response->getHeaders()['last-modified'][0] ?? null) {
                    $catalogue->setCatalogueMetadata('last-modified', $lastModified, $domain);
                }

                $translatorBag->addCatalogue($catalogue);
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        $catalogue = $translatorBag->getCatalogue($this->defaultLocale);

        if (!$catalogue) {
            $catalogue = $translatorBag->getCatalogues()[0];
        }

        $responses = [];

        foreach (array_keys($catalogue->all()) as $domain) {
            foreach ($this->getAssetsIds($domain) as $id) {
                $responses[$id] = $this->client->request('DELETE', sprintf('assets/%s.json', rawurlencode($id)));
            }
        }

        foreach ($responses as $key => $response) {
            if (403 === $statusCode = $response->getStatusCode()) {
                $this->logger->error('The API key used does not have sufficient permissions to delete assets.');
            }

            if (200 !== $statusCode && 404 !== $statusCode) {
                $this->logger->error(sprintf('Unable to delete translation key "%s" to Loco: "%s".', $key, $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException(sprintf('Unable to delete translation key "%s" to Loco.', $key), $response);
                }
            }
        }
    }

    /**
     * Returns array of internal Loco's unique ids.
     */
    private function getAssetsIds(string $domain): array
    {
        $response = $this->client->request('GET', 'assets', ['query' => ['filter' => $domain]]);

        if (200 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to get assets from Loco: "%s".', $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException('Unable to get assets from Loco.', $response);
            }
        }

        return array_map(fn ($asset) => $asset['id'], $response->toArray(false));
    }

    private function createAssets(array $keys, string $domain): array
    {
        $responses = $createdIds = [];

        foreach ($keys as $key) {
            $responses[$key] = $this->client->request('POST', 'assets', [
                'body' => [
                    'id' => $domain.'__'.$key, // must be globally unique, not only per domain
                    'text' => $key,
                    'type' => 'text',
                    'default' => 'untranslated',
                ],
            ]);
        }

        foreach ($responses as $key => $response) {
            if (201 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to add new translation key "%s" to Loco: (status code: "%s") "%s".', $key, $statusCode, $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException(sprintf('Unable to add new translation key "%s" to Loco: (status code: "%s").', $key, $statusCode), $response);
                }
            } else {
                $createdIds[] = $response->toArray(false)['id'];
            }
        }

        return $createdIds;
    }

    private function translateAssets(array $translations, string $locale): void
    {
        $responses = [];

        foreach ($translations as $id => $message) {
            $responses[$id] = $this->client->request('POST', sprintf('translations/%s/%s', rawurlencode($id), rawurlencode($locale)), [
                'body' => $message,
                'headers' => ['Content-Type' => 'text/plain'],
            ]);
        }

        foreach ($responses as $id => $response) {
            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to add translation for key "%s" in locale "%s" to Loco: "%s".', $id, $locale, $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException(sprintf('Unable to add translation for key "%s" in locale "%s" to Loco.', $id, $locale), $response);
                }
            }
        }
    }

    private function tagsAssets(array $ids, string $tag): void
    {
        if (!\in_array($tag, $this->getTags(), true)) {
            $this->createTag($tag);
        }

        // Separate ids with and without comma.
        $idsWithComma = $idsWithoutComma = [];
        foreach ($ids as $id) {
            if (str_contains($id, ',')) {
                $idsWithComma[] = $id;
            } else {
                $idsWithoutComma[] = $id;
            }
        }

        if ([] !== $idsWithoutComma) {
            // Set tags for all ids without comma.
            $response = $this->client->request('POST', sprintf('tags/%s.json', rawurlencode($tag)), [
                'body' => implode(',', $idsWithoutComma),
            ]);

            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to tag assets with "%s" on Loco: "%s".', $tag, $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException(sprintf('Unable to tag assets with "%s" on Loco.', $tag), $response);
                }
            }
        }

        // Set tags for each id with comma one by one.
        foreach ($idsWithComma as $id) {
            $response = $this->client->request('POST', sprintf('assets/%s/tags', rawurlencode($id)), [
                'body' => ['name' => $tag],
            ]);

            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to tag asset "%s" with "%s" on Loco: "%s".', $id, $tag, $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException(sprintf('Unable to tag asset "%s" with "%s" on Loco.', $id, $tag), $response);
                }
            }
        }
    }

    private function createTag(string $tag): void
    {
        $response = $this->client->request('POST', 'tags.json', [
            'body' => [
                'name' => $tag,
            ],
        ]);

        if (201 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to create tag "%s" on Loco: "%s".', $tag, $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException(sprintf('Unable to create tag "%s" on Loco.', $tag), $response);
            }
        }
    }

    private function getTags(): array
    {
        $response = $this->client->request('GET', 'tags.json');
        $content = $response->toArray(false);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to get tags on Loco: "%s".', $response->getContent(false)), $response);
        }

        return $content ?: [];
    }

    private function createLocale(string $locale): void
    {
        $response = $this->client->request('POST', 'locales', [
            'body' => [
                'code' => $locale,
            ],
        ]);

        if (201 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to create locale "%s" on Loco: "%s".', $locale, $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException(sprintf('Unable to create locale "%s" on Loco.', $locale), $response);
            }
        }
    }

    private function getLocales(): array
    {
        $response = $this->client->request('GET', 'locales');
        $content = $response->toArray(false);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to get locales on Loco: "%s".', $response->getContent(false)), $response);
        }

        return array_reduce($content, function ($carry, $locale) {
            $carry[] = $locale['code'];

            return $carry;
        }, []);
    }

    private function retrieveKeyFromId(string $id, string $domain): string
    {
        if (str_starts_with($id, $domain.'__')) {
            return substr($id, \strlen($domain) + 2);
        }

        return $id;
    }
}
