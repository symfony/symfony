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
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
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
 *
 * @experimental in 5.3
 */
final class LocoProvider implements ProviderInterface
{
    private $client;
    private $loader;
    private $logger;
    private $defaultLocale;
    private $endpoint;

    public function __construct(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint)
    {
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->endpoint = $endpoint;
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
            $createdIds = $this->createAssets(array_keys($messages));
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
                $ids = $this->getAssetsIds($domain);
                $this->translateAssets(array_combine($ids, array_values($messages)), $locale);
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $domains = $domains ?: ['*'];
        $translatorBag = new TranslatorBag();
        $responses = [];

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $responses[] = [
                    'response' => $this->client->request('GET', sprintf('export/locale/%s.xlf', rawurlencode($locale)), [
                        'query' => [
                            'filter' => $domain,
                            'status' => 'translated',
                        ],
                    ]),
                    'locale' => $locale,
                    'domain' => $domain,
                ];
            }
        }

        foreach ($responses as $response) {
            $locale = $response['locale'];
            $domain = $response['domain'];
            $response = $response['response'];

            if (404 === $response->getStatusCode()) {
                $this->logger->warning(sprintf('Locale "%s" for domain "%s" does not exist in Loco.', $locale, $domain));
                continue;
            }

            $responseContent = $response->getContent(false);

            if (200 !== $response->getStatusCode()) {
                throw new ProviderException('Unable to read the Loco response: '.$responseContent, $response);
            }

            $translatorBag->addCatalogue($this->loader->load($responseContent, $locale, $domain));
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
                $responses[$id] = $this->client->request('DELETE', sprintf('assets/%s.json', $id));
            }
        }

        foreach ($responses as $key => $response) {
            if (403 === $response->getStatusCode()) {
                $this->logger->error('The API key used does not have sufficient permissions to delete assets.');
            }

            if (200 !== $response->getStatusCode() && 404 !== $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to delete translation key "%s" to Loco: "%s".', $key, $response->getContent(false)));
            }
        }
    }

    /**
     * Returns array of internal Loco's unique ids.
     */
    private function getAssetsIds(string $domain): array
    {
        $response = $this->client->request('GET', 'assets', ['query' => ['filter' => $domain]]);

        if (200 !== $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to get assets from Loco: "%s".', $response->getContent(false)));
        }

        return array_map(function ($asset) {
            return $asset['id'];
        }, $response->toArray(false));
    }

    private function createAssets(array $keys): array
    {
        $responses = $createdIds = [];

        foreach ($keys as $key) {
            $responses[$key] = $this->client->request('POST', 'assets', [
                'body' => [
                    'text' => $key,
                    'type' => 'text',
                    'default' => 'untranslated',
                ],
            ]);
        }

        foreach ($responses as $key => $response) {
            if (201 !== $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to add new translation key "%s" to Loco: (status code: "%s") "%s".', $key, $response->getStatusCode(), $response->getContent(false)));
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
            $responses[$id] = $this->client->request('POST', sprintf('translations/%s/%s', $id, $locale), [
                'body' => $message,
            ]);
        }

        foreach ($responses as $id => $response) {
            if (200 !== $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to add translation for key "%s" in locale "%s" to Loco: "%s".', $id, $locale, $response->getContent(false)));
            }
        }
    }

    private function tagsAssets(array $ids, string $tag): void
    {
        if (!\in_array($tag, $this->getTags(), true)) {
            $this->createTag($tag);
        }

        $response = $this->client->request('POST', sprintf('tags/%s.json', $tag), [
            'body' => implode(',', $ids),
        ]);

        if (200 !== $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to tag assets with "%s" on Loco: "%s".', $tag, $response->getContent(false)));
        }
    }

    private function createTag(string $tag): void
    {
        $response = $this->client->request('POST', 'tags.json', [
            'body' => [
                'name' => $tag,
            ],
        ]);

        if (201 !== $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to create tag "%s" on Loco: "%s".', $tag, $response->getContent(false)));
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

        if (201 !== $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to create locale "%s" on Loco: "%s".', $locale, $response->getContent(false)));
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
}
