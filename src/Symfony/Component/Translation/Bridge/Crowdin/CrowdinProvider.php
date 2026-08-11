<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Crowdin;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Andrii Bodnar <andrii.bodnar@crowdin.com>
 *
 * In Crowdin:
 *  * Filenames refer to Symfony's translation domains;
 *  * Identifiers refer to Symfony's translation keys;
 *  * Translations refer to Symfony's translated messages
 */
final class CrowdinProvider implements ProviderInterface
{
    private const IMPORT_POLL_TIMEOUT_SECONDS = 300;

    private readonly ?string $projectId;

    /**
     * @param string $projectId
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoaderInterface $loader,
        private readonly LoggerInterface $logger,
        private readonly XliffFileDumper $xliffFileDumper,
        private readonly string $defaultLocale,
        private readonly string $endpoint,
        /* string $projectId, */
    ) {
        if (\func_num_args() < 7) {
            trigger_deprecation('symfony/crowdin-translation-provider', '8.2', 'The "%s()" method will have a new "string $projectId" argument in version 9.0, not defining it is deprecated.', __METHOD__);

            $this->projectId = null;
        } else {
            $this->projectId = func_get_arg(6);
        }
    }

    public function __toString(): string
    {
        return \sprintf('crowdin://%s', $this->endpoint);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $fileIds = $this->getFileIds();
        [$languageIds, $sourceLanguageId] = $this->getLanguageIds();

        $defaultLocaleCatalogue = $translatorBag->getCatalogue($this->defaultLocale);
        foreach ($defaultLocaleCatalogue->getDomains() as $domain) {
            $content = $this->xliffFileDumper->formatCatalogue($defaultLocaleCatalogue, $domain, ['default_locale' => $this->defaultLocale]);

            if ($fileId = $fileIds[$domain] ?? null) {
                $sourceFileInfo = $this->downloadSourceFile($fileId);
                $sourceFile = $this->client->request('GET', $sourceFileInfo->toArray()['data']['url']);

                $providerCatalogue = $this->loader->load($sourceFile->getContent(), $this->defaultLocale, $domain);
                $allMessages = array_merge($providerCatalogue->all($domain), $defaultLocaleCatalogue->all($domain));

                $content = $this->xliffFileDumper->formatCatalogue(
                    new MessageCatalogue($this->defaultLocale, [$domain => $allMessages]),
                    $domain,
                    ['default_locale' => $this->defaultLocale],
                );

                $this->updateFile($fileId, $domain, $content);
            } else {
                $file = $this->addFile($domain, $content);

                $fileIds[$domain] = $file['id'];
            }
        }

        $responses = [];

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locale = $catalogue->getLocale();

            if ($locale === $this->defaultLocale) {
                continue;
            }
            if (!isset($languageIds[$locale])) {
                $this->logger->warning(\sprintf('Ignored "%s" locale because it is not configured or mapped in the project.', $locale));

                continue;
            }
            if ($sourceLanguageId === $languageIds[$locale]) {
                continue;
            }

            foreach ($catalogue->getDomains() as $domain) {
                if (!$catalogue->all($domain)) {
                    continue;
                }

                if ($fileId = $fileIds[$domain] ?? null) {
                    $responses[] = $this->importTranslations(
                        $fileId,
                        $domain,
                        $this->xliffFileDumper->formatCatalogue($catalogue, $domain, ['default_locale' => $this->defaultLocale]),
                        $languageIds[$locale],
                    );
                }
            }
        }

        $this->waitForImportCompletion($responses);
    }

    private function waitForImportCompletion(array $responses): void
    {
        $deadline = hrtime(true) + self::IMPORT_POLL_TIMEOUT_SECONDS * 1_000_000_000;

        while ($responses) {
            foreach ($responses as $index => $response) {
                if (202 !== $statusCode = $response->getStatusCode()) {
                    $this->logger->error(\sprintf('Unable to upload translations to Crowdin: "%s".', $response->getContent(false)));

                    if (500 <= $statusCode) {
                        throw new ProviderException('Unable to upload translations to Crowdin.', $response);
                    }

                    unset($responses[$index]);
                    continue;
                }

                $importStatusResponse = $this->checkImportTranslationsStatus($response->toArray()['data']['identifier']);

                if (200 !== $importStatusResponse->getStatusCode()) {
                    $this->logger->error(\sprintf('Unable to check import translations status: "%s".', $importStatusResponse->getContent(false)));

                    unset($responses[$index]);
                    continue;
                }

                $importStatusData = $importStatusResponse->toArray()['data'];
                $status = $importStatusData['status'] ?? 'unknown';

                if ('finished' === $status) {
                    unset($responses[$index]);
                    continue;
                }

                if ('failed' === $status) {
                    $message = $importStatusData['attributes']['error']['message'] ?? null;

                    if ($message) {
                        $this->logger->error(\sprintf('Unable to upload translations to Crowdin: "%s".', $message));
                    } else {
                        $this->logger->error('Unable to upload translations to Crowdin.');
                    }

                    unset($responses[$index]);
                    continue;
                }

                if (!\in_array($status, ['in_progress', 'created'], true)) {
                    $this->logger->error(\sprintf('Unable to upload translations to Crowdin: unexpected import status "%s".', $status));
                    unset($responses[$index]);
                }
            }

            if (!$responses) {
                break;
            }

            if (hrtime(true) >= $deadline) {
                throw new ProviderException(\sprintf('Timed out after %d seconds while waiting for Crowdin to finish importing translations.', self::IMPORT_POLL_TIMEOUT_SECONDS), reset($responses));
            }

            sleep(1);
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $fileIds = $this->getFileIds();
        [$languageIds, $sourceLanguageId] = $this->getLanguageIds();

        $domains = $domains ?: array_keys($fileIds);

        // flipping keeps one locale per language, the mapped one when there is a mapping
        $locales = $locales ?: array_flip($languageIds);
        foreach ($locales as $i => $locale) {
            if (!isset($languageIds[$locale])) {
                $this->logger->warning(\sprintf('Ignored "%s" locale because it is not configured or mapped in the project.', $locale));

                unset($locales[$i]);
            }
        }

        $translatorBag = new TranslatorBag();
        $responses = [];

        foreach ($domains as $domain) {
            if (!$fileId = $fileIds[$domain] ?? null) {
                continue;
            }

            foreach ($locales as $locale) {
                if ($sourceLanguageId === $languageIds[$locale]) {
                    $response = $this->downloadSourceFile($fileId);
                } else {
                    $response = $this->exportProjectTranslations($languageIds[$locale], $fileId);
                }

                $responses[] = [$response, $locale, $domain];
            }
        }

        /** @var ResponseInterface $response */
        $downloads = [];
        foreach ($responses as [$response, $locale, $domain]) {
            if (204 === $response->getStatusCode()) {
                $this->logger->error(\sprintf('No content in exported file: "%s".', $response->getContent(false)));

                continue;
            }

            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(\sprintf('Unable to export file: "%s".', $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException('Unable to export file.', $response);
                }

                continue;
            }

            $response = $this->client->request('GET', $response->toArray()['data']['url']);
            $downloads[] = [$response, $locale, $domain];
        }

        foreach ($downloads as [$response, $locale, $domain]) {
            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(\sprintf('Unable to download file content: "%s".', $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException('Unable to download file content.', $response);
                }

                continue;
            }

            $translatorBag->addCatalogue($this->loader->load($response->getContent(), $locale, $domain));
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        $fileIds = $this->getFileIds();
        $defaultCatalogue = $translatorBag->getCatalogue($this->defaultLocale);

        foreach ($defaultCatalogue->all() as $domain => $messages) {
            if (!$fileId = $fileIds[$domain] ?? null) {
                continue;
            }

            $sourceFileInfo = $this->downloadSourceFile($fileId);
            $sourceFile = $this->client->request('GET', $sourceFileInfo->toArray()['data']['url']);

            $providerCatalogue = $this->loader->load($sourceFile->getContent(), $this->defaultLocale, $domain);
            $existingMessages = array_diff($providerCatalogue->all($domain), $messages);

            $content = $this->xliffFileDumper->formatCatalogue(
                new MessageCatalogue($this->defaultLocale, [$domain => $existingMessages]),
                $domain,
                ['default_locale' => $this->defaultLocale],
            );

            try {
                $file = $this->updateFile($fileId, $domain, $content);

                if (null === $file) {
                    $this->logger->warning(
                        \sprintf('Unable to update file "%d" and domain "%s".', $fileId, $domain)
                    );
                }
            } catch (ProviderException $e) {
                throw new ProviderException(\sprintf('Unable to update file "%d" and domain "%s": "%s".', $fileId, $domain, $e->getMessage()), $e->getResponse(), previous: $e);
            }
        }
    }

    /**
     * @see https://support.crowdin.com/developer/api/v2/#tag/Source-Files/operation/api.projects.files.getMany (Crowdin API)
     * @see https://support.crowdin.com/developer/enterprise/api/v2/#tag/Source-Files/operation/api.projects.files.getMany (Crowdin Enterprise API)
     */
    private function addFile(string $domain, string $content): ?array
    {
        $response = $this->client->request('POST', $this->getProjectEndpoint('files'), [
            'json' => [
                'storageId' => $this->addStorage($domain, $content),
                'name' => \sprintf('%s.%s', $domain, 'xlf'),
            ],
        ]);

        if (201 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(\sprintf('Unable to create a File in Crowdin for domain "%s": "%s".', $domain, $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException(\sprintf('Unable to create a File in Crowdin for domain "%s".', $domain), $response);
            }

            return null;
        }

        return $response->toArray()['data'];
    }

    /**
     * @see https://support.crowdin.com/developer/api/v2/#tag/Source-Files/operation/api.projects.files.put (Crowdin API)
     * @see https://support.crowdin.com/developer/enterprise/api/v2/#tag/Source-Files/operation/api.projects.files.put (Crowdin Enterprise API)
     */
    private function updateFile(int $fileId, string $domain, string $content): ?array
    {
        $response = $this->client->request('PUT', $this->getProjectEndpoint('files/'.$fileId), [
            'json' => [
                'storageId' => $this->addStorage($domain, $content),
            ],
        ]);

        if (200 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(\sprintf('Unable to update file in Crowdin for file ID "%d" and domain "%s": "%s".', $fileId, $domain, $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException(\sprintf('Unable to update file in Crowdin for file ID "%d" and domain "%s".', $fileId, $domain), $response);
            }

            return null;
        }

        return $response->toArray()['data'];
    }

    /**
     * @see https://support.crowdin.com/developer/api/v2/#tag/Translations/operation/api.projects.translations.imports (Crowdin API)
     * @see https://support.crowdin.com/developer/enterprise/api/v2/#tag/Translations/operation/api.projects.translations.enterprise.imports (Crowdin Enterprise API)
     */
    private function importTranslations(int $fileId, string $domain, string $content, string $languageId): ResponseInterface
    {
        return $this->client->request('POST', $this->getProjectEndpoint('translations/imports'), [
            'json' => [
                'storageId' => $this->addStorage($domain, $content),
                'languageIds' => [$languageId],
                'fileId' => $fileId,
            ],
        ]);
    }

    /**
     * @see https://support.crowdin.com/developer/api/v2/#tag/Translations/operation/api.projects.translations.imports.get (Crowdin API)
     * @see https://support.crowdin.com/developer/enterprise/api/v2/#tag/Translations/operation/api.projects.translations.enterprise.imports.get (Crowdin Enterprise API)
     */
    private function checkImportTranslationsStatus(string $importTranslationId): ResponseInterface
    {
        return $this->client->request('GET', $this->getProjectEndpoint('translations/imports/'.$importTranslationId));
    }

    /**
     * @see https://support.crowdin.com/developer/api/v2/#tag/Translations/operation/api.projects.translations.exports.post (Crowdin API)
     * @see https://support.crowdin.com/developer/enterprise/api/v2/#tag/Translations/operation/api.projects.translations.exports.post (Crowdin Enterprise API)
     */
    private function exportProjectTranslations(string $languageId, int $fileId): ResponseInterface
    {
        return $this->client->request('POST', $this->getProjectEndpoint('translations/exports'), [
            'json' => [
                'targetLanguageId' => $languageId,
                'fileIds' => [$fileId],
            ],
        ]);
    }

    /**
     * @see https://support.crowdin.com/developer/api/v2/#tag/Source-Files/operation/api.projects.files.download.get (Crowdin API)
     * @see https://support.crowdin.com/developer/enterprise/api/v2/#tag/Source-Files/operation/api.projects.files.download.get (Crowdin Enterprise API)
     */
    private function downloadSourceFile(int $fileId): ResponseInterface
    {
        return $this->client->request('GET', $this->getProjectEndpoint(\sprintf('files/%d/download', $fileId)));
    }

    /**
     * @see https://support.crowdin.com/developer/api/v2/#tag/Storage/operation/api.storages.post (Crowdin API)
     * @see https://support.crowdin.com/developer/enterprise/api/v2/#tag/Storage/operation/api.storages.post (Crowdin Enterprise API)
     */
    private function addStorage(string $domain, string $content): int
    {
        $response = $this->client->request('POST', \sprintf('%sstorages', $this->projectId ? '' : '../../'), [
            'headers' => [
                'Crowdin-API-FileName' => urlencode(\sprintf('%s.%s', $domain, 'xlf')),
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $content,
        ]);

        if (201 !== $response->getStatusCode()) {
            throw new ProviderException(\sprintf('Unable to add a Storage in Crowdin for domain "%s".', $domain), $response);
        }

        return $response->toArray()['data']['id'];
    }

    /**
     * @see https://support.crowdin.com/developer/api/v2/#tag/Source-Files/operation/api.projects.files.getMany (Crowdin API)
     * @see https://support.crowdin.com/developer/enterprise/api/v2/#tag/Source-Files/operation/api.projects.files.getMany (Crowdin Enterprise API)
     */
    private function getFileIds(): array
    {
        $response = $this->client->request('GET', $this->getProjectEndpoint('files'));

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException('Unable to list Crowdin files.', $response);
        }

        $fileList = $response->toArray()['data'];
        $result = [];
        foreach ($fileList as $file) {
            if (str_ends_with($file['data']['name'], '.xlf')) {
                $result[substr($file['data']['name'], 0, -4)] = $file['data']['id'];
            }
        }

        return $result;
    }

    /**
     * @return array{array<string, string>, string} The language IDs indexed by locale, then the source language ID
     *
     * @see https://support.crowdin.com/developer/api/v2/#tag/Projects/operation/api.projects.get (Crowdin API)
     * @see https://support.crowdin.com/developer/enterprise/api/v2/#tag/Projects-and-Groups/operation/api.projects.get (Crowdin Enterprise API)
     */
    private function getLanguageIds(): array
    {
        $response = $this->client->request('GET', $this->getProjectEndpoint());

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException('Unable to get project settings.', $response);
        }

        $projectInfo = $response->toArray()['data'];

        $languageIds = [$projectInfo['sourceLanguageId'], ...$projectInfo['targetLanguageIds']];
        $languageIds = array_combine(
            array_map(static fn (string $languageId) => str_replace('-', '_', $languageId), $languageIds),
            $languageIds,
        );

        if (!isset($projectInfo['languageMapping'])) {
            $this->logger->warning('API key does not allow to access language mapping.');

            return [$languageIds, $projectInfo['sourceLanguageId']];
        }

        foreach ($projectInfo['languageMapping'] as $languageId => $mapping) {
            if (isset($mapping['locale'])) {
                // a language keeps its ID as an alias, so both spellings are accepted
                $languageIds[str_replace('-', '_', $mapping['locale'])] = $languageId;
            } else {
                $this->logger->warning(\sprintf('Ignored "%s" mapping because it has no "locale" placeholder.', $languageId));
            }
        }

        return [$languageIds, $projectInfo['sourceLanguageId']];
    }

    private function getProjectEndpoint(string $endpoint = ''): string
    {
        return $this->projectId
            ? rtrim(\sprintf('projects/%s/%s', $this->projectId, $endpoint), '/')
            : $endpoint
        ;
    }
}
