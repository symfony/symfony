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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProvider;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 *
 * In POeditor:
 * Terms refers to Symfony's translation keys;
 * Translations refers to Symfony's translated messages;
 * tags refers to Symfony's translation domains
 */
final class PoEditorProvider extends AbstractProvider
{
    protected const HOST = 'api.poeditor.com/v2';

    private $projectId;
    private $apiKey;
    private $loader;
    private $logger;
    private $defaultLocale;

    public function __construct(string $projectId, string $apiKey, HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->projectId = $projectId;
        $this->apiKey = $apiKey;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf('poeditor://%s', $this->getEndpoint());
    }

    public function getName(): string
    {
        return 'poeditor';
    }

    public function write(TranslatorBag $translatorBag, bool $override = false): void
    {
        $terms = $translations = [];
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            foreach ($catalogue->all() as $domain => $messages) {
                $locale = $catalogue->getLocale();

                foreach ($messages as $id => $message) {
                    $terms[] = [
                        'term' => $id,
                        'reference' => $id,
                        'tags' => [$domain],
                    ];
                    $translations[$locale][] = [
                        'term' => $id,
                        'translation' => [
                            'content' => $message,
                        ],
                    ];
                }
                $this->addTerms($terms);
            }
            if ($override) {
                $this->updateTranslations($translations[$catalogue->getLocale()], $catalogue->getLocale());
            } else {
                $this->addTranslations($translations[$catalogue->getLocale()], $catalogue->getLocale());
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            $exportResponse = $this->client->request('POST', sprintf('https://%s/projects/export', $this->getEndpoint()), [
                'headers' => $this->getDefaultHeaders(),
                'body' => [
                    'api_token' => $this->apiKey,
                    'id' => $this->projectId,
                    'language' => $locale,
                    'type' => 'xlf',
                    'filters' => json_encode(['translated']),
                    'tags' => json_encode($domains),
                ],
             ]);

            $exportResponseContent = $exportResponse->getContent(false);

            if (Response::HTTP_OK !== $exportResponse->getStatusCode()) {
                throw new ProviderException('Unable to read the POEditor response: '.$exportResponseContent, $exportResponse);
            }

            $response = $this->client->request('GET', json_decode($exportResponseContent, true)['result']['url'], [
                'headers' => $this->getDefaultHeaders(),
            ]);

            foreach ($domains as $domain) {
                $translatorBag->addCatalogue($this->loader->load($response->getContent(), $locale, $domain));
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBag $translations): void
    {
        $deletedIds = $termsToDelete = [];

        foreach ($translations->all() as $locale => $domainMessages) {
            foreach ($domainMessages as $domain => $messages) {
                foreach ($messages as $id => $message) {
                    if (\in_array($id, $deletedIds)) {
                        continue;
                    }

                    $deletedIds = $id;
                    $termsToDelete = [
                        'term' => $id,
                    ];
                }
            }
        }

        $this->deleteTerms($termsToDelete);
    }

    private function addTerms(array $terms): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/terms/add', $this->getEndpoint()), [
            'headers' => $this->getDefaultHeaders(),
            'body' => [
                'api_token' => $this->apiKey,
                'id' => $this->projectId,
                'data' => json_encode($terms),
            ],
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add new translation key (%s) to POEditor: (status code: "%s") "%s".', $id, $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    private function addTranslations(array $translations, string $locale): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/translations/add', $this->getEndpoint()), [
            'headers' => $this->getDefaultHeaders(),
            'body' => [
                'api_token' => $this->apiKey,
                'id' => $this->projectId,
                'language' => $locale,
                'data' => json_encode($translations),
            ],
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add translation messages to POEditor: "%s".', $response->getContent(false)), $response);
        }
    }

    private function updateTranslations(array $translations, string $locale): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/languages/update', $this->getEndpoint()), [
            'headers' => $this->getDefaultHeaders(),
            'body' => [
                'api_token' => $this->apiKey,
                'id' => $this->projectId,
                'language' => $locale,
                'data' => json_encode($translations),
            ],
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add translation messages to POEditor: "%s".', $response->getContent(false)), $response);
        }
    }

    private function deleteTerms(array $ids): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/terms/delete', $this->getEndpoint()), [
            'headers' => $this->getDefaultHeaders(),
            'body' => [
                'api_token' => $this->apiKey,
                'id' => $this->projectId,
                'data' => json_encode($ids),
            ],
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to delete translation keys on POEditor: "%s".', $response->getContent(false)), $response);
        }
    }
}
