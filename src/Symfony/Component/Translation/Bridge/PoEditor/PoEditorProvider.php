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
 * In POEditor:
 *  * Terms refer to Symfony's translation keys;
 *  * Translations refer to Symfony's translated messages;
 *  * Context fields refer to Symfony's translation domains.
 *
 * POEditor's API always returns 200 status code, even in case of failure.
 */
final class PoEditorProvider implements ProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoaderInterface $loader,
        private readonly LoggerInterface $logger,
        private readonly string $defaultLocale,
        private readonly string $endpoint,
    ) {
    }

    public function __toString(): string
    {
        return \sprintf('poeditor://%s', $this->endpoint);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $languages = $this->addMissingLanguages($translatorBag);

        $defaultCatalogue = $translatorBag->getCatalogue($this->defaultLocale);

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
            $language = self::getPoEditorLocale($catalogue->getLocale(), $languages);
            foreach ($catalogue->all() as $domain => $messages) {
                foreach ($messages as $id => $message) {
                    $translationsToAdd[$language][] = [
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
        $domains = $domains ?: $this->getDomains();
        $languages = $this->getLanguages();
        $locales = $locales
            ? array_combine($locales, array_map(static fn (string $locale) => self::getPoEditorLocale($locale, $languages), $locales))
            : array_combine(array_map(self::getSymfonyLocale(...), $languages), $languages);

        $translatorBag = new TranslatorBag();
        $exportResponses = $downloadResponses = [];

        foreach ($locales as $locale => $language) {
            foreach ($domains as $domain) {
                $response = $this->client->request('POST', 'projects/export', [
                    'body' => [
                        'language' => $language,
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
                $this->logger->error('Unable to read the POEditor response: '.$response->getContent(false));
                continue;
            }

            $fileUrl = $responseContent['result']['url'];
            $downloadResponses[] = [$this->client->request('GET', $fileUrl), $locale, $domain, $fileUrl];
        }

        foreach ($downloadResponses as [$response, $locale, $domain, $fileUrl]) {
            $responseContent = $response->getContent(false);

            if (200 !== $response->getStatusCode()) {
                $this->logger->error('Unable to download the POEditor exported file: '.$responseContent);
                continue;
            }

            if (!$responseContent) {
                $this->logger->error(\sprintf('The exported file "%s" from POEditor is empty.', $fileUrl));
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
                    if (\in_array($id, $deletedIds[$domain] ?? [], true)) {
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
                'data' => json_encode($terms),
            ],
        ]);

        if (200 !== $response->getStatusCode() || '200' !== (string) $response->toArray(false)['response']['code']) {
            throw new ProviderException(\sprintf('Unable to add new translation keys to POEditor: (status code: "%s") "%s".', $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    private function addTranslations(array $translationsPerLocale): void
    {
        $responses = [];

        foreach ($translationsPerLocale as $locale => $translations) {
            $responses[] = $this->client->request('POST', 'translations/add', [
                'body' => [
                    'language' => $locale,
                    'data' => json_encode($translations),
                ],
            ]);
        }

        foreach ($responses as $response) {
            if (200 !== $response->getStatusCode() || '200' !== (string) $response->toArray(false)['response']['code']) {
                $this->logger->error(\sprintf('Unable to add translation messages to POEditor: "%s".', $response->getContent(false)));
            }
        }
    }

    private function deleteTerms(array $ids): void
    {
        $response = $this->client->request('POST', 'terms/delete', [
            'body' => [
                'data' => json_encode($ids),
            ],
        ]);

        if (200 !== $response->getStatusCode() || '200' !== (string) $response->toArray(false)['response']['code']) {
            throw new ProviderException(\sprintf('Unable to delete translation keys on POEditor: "%s".', $response->getContent(false)), $response);
        }
    }

    /**
     * POEditor has no endpoint listing the tags, so they are read from the terms.
     *
     * @return string[]
     */
    private function getDomains(): array
    {
        $response = $this->client->request('POST', 'terms/list', [
            'body' => [],
        ]);

        if (200 !== $response->getStatusCode() || '200' !== (string) $response->toArray(false)['response']['code']) {
            throw new ProviderException(\sprintf('Unable to list the translation keys on POEditor: "%s".', $response->getContent(false)), $response);
        }

        $terms = $response->toArray(false)['result']['terms'] ?? [];

        return array_values(array_unique(array_merge([], ...array_column($terms, 'tags'))));
    }

    /**
     * @return string[] the language codes of the project, in POEditor's own spelling
     */
    private function getLanguages(): array
    {
        $response = $this->client->request('POST', 'languages/list', [
            'body' => [],
        ]);

        if (200 !== $response->getStatusCode() || '200' !== (string) $response->toArray(false)['response']['code']) {
            throw new ProviderException(\sprintf('Unable to list the languages on POEditor: "%s".', $response->getContent(false)), $response);
        }

        return array_column($response->toArray(false)['result']['languages'] ?? [], 'code');
    }

    /**
     * Adding a language POEditor already knows about fails, so only the missing ones are sent.
     *
     * @return string[] the language codes of the project, the added ones included
     */
    private function addMissingLanguages(TranslatorBagInterface $translatorBag): array
    {
        $languages = $this->getLanguages();
        $responses = [];

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            if (!\in_array($language = self::getPoEditorLocale($catalogue->getLocale(), $languages), $languages, true)) {
                $languages[] = $language;
                $responses[] = [$language, $this->client->request('POST', 'languages/add', [
                    'body' => ['language' => $language],
                ])];
            }
        }

        foreach ($responses as [$language, $response]) {
            if (200 !== $response->getStatusCode() || '200' !== (string) $response->toArray(false)['response']['code']) {
                $this->logger->error(\sprintf('Unable to add the "%s" language to POEditor: "%s".', $language, $response->getContent(false)));
            }
        }

        return $languages;
    }

    /**
     * POEditor spells most of its codes in lower case, "pt-br", but not all of them, "zh-Hans".
     * The project's own list is therefore the reference, and lower case is the fallback for a
     * language that is not in the project yet.
     *
     * @param string[] $languages
     */
    private static function getPoEditorLocale(string $locale, array $languages): string
    {
        $code = str_replace('_', '-', $locale);

        foreach ($languages as $language) {
            if (0 === strcasecmp($code, $language)) {
                return $language;
            }
        }

        return strtolower($code);
    }

    private static function getSymfonyLocale(string $code): string
    {
        $subtags = explode('-', strtolower($code));
        $language = array_shift($subtags);

        foreach ($subtags as $i => $subtag) {
            $subtags[$i] = match (\strlen($subtag)) {
                2 => strtoupper($subtag),
                4 => ucfirst($subtag),
                default => $subtag,
            };
        }

        return implode('_', [$language, ...$subtags]);
    }
}
