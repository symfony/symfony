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
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * In PoEditor:
 *  * Terms refer to Symfony's translation keys;
 *  * Translations refer to Symfony's translated messages;
 *  * Context fields refer to Symfony's translation domains
 *
 * PoEditor's API always returns 200 status code, even in case of failure.
 *
 * @experimental in 5.3
 */
final class PoEditorProvider implements ProviderInterface
{
    private $apiKey;
    private $projectId;
    private $client;
    private $loader;
    private $logger;
    private $defaultLocale;
    private $endpoint;

    public function __construct(string $apiKey, string $projectId, HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint)
    {
        $this->apiKey = $apiKey;
        $this->projectId = $projectId;
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->endpoint = $endpoint;
    }

    public function __toString(): string
    {
        return sprintf('poeditor://%s', $this->endpoint);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $defaultCatalogue = $translatorBag->getCatalogue($this->defaultLocale);

        if (!$defaultCatalogue) {
            $defaultCatalogue = $translatorBag->getCatalogues()[0];
        }

        $terms = $translationsToAdd = [];
        foreach ($defaultCatalogue->all() as $domain => $messages) {
            foreach ($messages as $id => $message) {
                $terms[] = [
                    'term' => $id,
                    'reference' => $id,
                    // tags field is mandatory to export all translations in read method.
                    'tags' => [$domain],
                    'context' => $domain,
                ];
            }
        }
        $this->addTerms($terms);

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locale = $catalogue->getLocale();
            foreach ($catalogue->all() as $domain => $messages) {
                foreach ($messages as $id => $message) {
                    $translationsToAdd[$locale][] = [
                        'term' => $id,
                        'context' => $domain,
                        'translation' => [
                            'content' => $message,
                        ],
                    ];
                }
            }
        }

        $this->addTranslations($translationsToAdd);
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();
        $exportResponses = $downloadResponses = [];

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $response = $this->client->request('POST', 'projects/export', [
                    'body' => [
                        'api_token' => $this->apiKey,
                        'id' => $this->projectId,
                        'language' => $locale,
                        'type' => 'xlf',
                        'filters' => json_encode(['translated']),
                        'tags' => json_encode([$domain]),
                    ],
                ]);
                $exportResponses[] = [$response, $locale, $domain];
            }
        }

        foreach ($exportResponses as [$response, $locale, $domain]) {
            $responseContent = $response->toArray(false);

            if (200 !== $response->getStatusCode() || '200' !== (string) $responseContent['response']['code']) {
                $this->logger->error('Unable to read the PoEditor response: '.$response->getContent(false));
                continue;
            }

            $fileUrl = $responseContent['result']['url'];
            $downloadResponses[] = [$this->client->request('GET', $fileUrl), $locale, $domain, $fileUrl];
        }

        foreach ($downloadResponses as [$response, $locale, $domain, $fileUrl]) {
            $responseContent = $response->getContent(false);

            if (200 !== $response->getStatusCode()) {
                $this->logger->error('Unable to download the PoEditor exported file: '.$responseContent);
                continue;
            }

            if (!$responseContent) {
                $this->logger->error(sprintf('The exported file "%s" from PoEditor is empty.', $fileUrl));
                continue;
            }

            $translatorBag->addCatalogue($this->loader->load($responseContent, $locale, $domain));
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        $deletedIds = $termsToDelete = [];

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            foreach ($catalogue->all() as $domain => $messages) {
                foreach ($messages as $id => $message) {
                    if (\array_key_exists($domain, $deletedIds) && \in_array($id, $deletedIds[$domain], true)) {
                        continue;
                    }

                    $deletedIds[$domain][] = $id;
                    $termsToDelete[] = [
                        'term' => $id,
                        'context' => $domain,
                    ];
                }
            }
        }

        $this->deleteTerms($termsToDelete);
    }

    private function addTerms(array $terms): void
    {
        $response = $this->client->request('POST', 'terms/add', [
            'body' => [
                'api_token' => $this->apiKey,
                'id' => $this->projectId,
                'data' => json_encode($terms),
            ],
        ]);

        if (200 !== $response->getStatusCode() || '200' !== (string) $response->toArray(false)['response']['code']) {
            throw new ProviderException(sprintf('Unable to add new translation keys to PoEditor: (status code: "%s") "%s".', $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    private function addTranslations(array $translationsPerLocale): void
    {
        $responses = [];

        foreach ($translationsPerLocale as $locale => $translations) {
            $responses = $this->client->request('POST', 'translations/add', [
                'body' => [
                    'api_token' => $this->apiKey,
                    'id' => $this->projectId,
                    'language' => $locale,
                    'data' => json_encode($translations),
                ],
            ]);
        }

        foreach ($responses as $response) {
            if (200 !== $response->getStatusCode() || '200' !== (string) $response->toArray(false)['response']['code']) {
                $this->logger->error(sprintf('Unable to add translation messages to PoEditor: "%s".', $response->getContent(false)));
            }
        }
    }

    private function deleteTerms(array $ids): void
    {
        $response = $this->client->request('POST', 'terms/delete', [
            'body' => [
                'api_token' => $this->apiKey,
                'id' => $this->projectId,
                'data' => json_encode($ids),
            ],
        ]);

        if (200 !== $response->getStatusCode() || '200' !== (string) $response->toArray(false)['response']['code']) {
            throw new ProviderException(sprintf('Unable to delete translation keys on PoEditor: "%s".', $response->getContent(false)), $response);
        }
    }
}
