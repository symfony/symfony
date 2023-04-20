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
    private HttpClientInterface $client;
    private LoaderInterface $loader;
    private LoggerInterface $logger;
    private XliffFileDumper $xliffFileDumper;
    private string $defaultLocale;
    private string $endpoint;

    public function __construct(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, XliffFileDumper $xliffFileDumper, string $defaultLocale, string $endpoint)
    {
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->xliffFileDumper = $xliffFileDumper;
        $this->defaultLocale = $defaultLocale;
        $this->endpoint = $endpoint;
    }

    public function __toString(): string
    {
        return sprintf('crowdin://%s', $this->endpoint);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $fileList = $this->getFileList();

        $responses = [];

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            foreach ($catalogue->getDomains() as $domain) {
                if (0 === \count($catalogue->all($domain))) {
                    continue;
                }

                $content = $this->xliffFileDumper->formatCatalogue($catalogue, $domain, ['default_locale' => $this->defaultLocale]);

                $fileId = $this->getFileIdByDomain($fileList, $domain);

                if ($catalogue->getLocale() === $this->defaultLocale) {
                    if (!$fileId) {
                        $file = $this->addFile($domain, $content);
                    } else {
                        $file = $this->updateFile($fileId, $domain, $content);
                    }

                    if (!$file) {
                        continue;
                    }

                    $fileList[$file['name']] = $file['id'];
                } else {
                    if (!$fileId) {
                        continue;
                    }

                    $responses[] = $this->uploadTranslations($fileId, $domain, $content, $catalogue->getLocale());
                }
            }
        }

        foreach ($responses as $response) {
            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to upload translations to Crowdin: "%s".', $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException('Unable to upload translations to Crowdin.', $response);
                }
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $fileList = $this->getFileList();

        $translatorBag = new TranslatorBag();
        $responses = [];

        $localeLanguageMap = $this->mapLocalesToLanguageId($locales);

        foreach ($domains as $domain) {
            $fileId = $this->getFileIdByDomain($fileList, $domain);

            if (!$fileId) {
                continue;
            }

            foreach ($locales as $locale) {
                if ($locale !== $this->defaultLocale) {
                    $response = $this->exportProjectTranslations($localeLanguageMap[$locale], $fileId);
                } else {
                    $response = $this->downloadSourceFile($fileId);
                }

                $responses[] = [$response, $locale, $domain];
            }
        }

        /* @var ResponseInterface $response */
        $downloads = [];
        foreach ($responses as [$response, $locale, $domain]) {
            if (204 === $response->getStatusCode()) {
                $this->logger->error(sprintf('No content in exported file: "%s".', $response->getContent(false)));

                continue;
            }

            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(sprintf('Unable to export file: "%s".', $response->getContent(false)));

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
                $this->logger->error(sprintf('Unable to download file content: "%s".', $response->getContent(false)));

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
        $fileList = $this->getFileList();
        $responses = [];

        $defaultCatalogue = $translatorBag->getCatalogue($this->defaultLocale);

        if (!$defaultCatalogue) {
            $defaultCatalogue = $translatorBag->getCatalogues()[0];
        }

        foreach ($defaultCatalogue->all() as $domain => $messages) {
            $fileId = $this->getFileIdByDomain($fileList, $domain);

            if (!$fileId) {
                continue;
            }

            $stringsMap = $this->mapStrings($fileId);

            foreach ($messages as $id => $message) {
                if (!\array_key_exists($id, $stringsMap)) {
                    continue;
                }

                $responses[] = $this->deleteString($stringsMap[$id]);
            }
        }

        foreach ($responses as $response) {
            if (404 === $response->getStatusCode()) {
                continue;
            }

            if (204 !== $statusCode = $response->getStatusCode()) {
                $this->logger->warning(sprintf('Unable to delete string: "%s".', $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException('Unable to delete string.', $response);
                }
            }
        }
    }

    private function getFileIdByDomain(array $filesMap, string $domain): ?int
    {
        return $filesMap[sprintf('%s.%s', $domain, 'xlf')] ?? null;
    }

    private function mapStrings(int $fileId): array
    {
        $result = [];

        $limit = 500;
        $offset = 0;

        do {
            $strings = $this->listStrings($fileId, $limit, $offset);

            foreach ($strings as $string) {
                $result[$string['data']['text']] = $string['data']['id'];
            }

            $offset += $limit;
        } while (\count($strings) > 0);

        return $result;
    }

    private function addFile(string $domain, string $content): ?array
    {
        $storageId = $this->addStorage($domain, $content);

        /**
         * @see https://developer.crowdin.com/api/v2/#operation/api.projects.files.getMany (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2/#operation/api.projects.files.getMany (Crowdin Enterprise API)
         */
        $response = $this->client->request('POST', 'files', [
            'json' => [
                'storageId' => $storageId,
                'name' => sprintf('%s.%s', $domain, 'xlf'),
            ],
        ]);

        if (201 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to create a File in Crowdin for domain "%s": "%s".', $domain, $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException(sprintf('Unable to create a File in Crowdin for domain "%s".', $domain), $response);
            }

            return null;
        }

        return $response->toArray()['data'];
    }

    private function updateFile(int $fileId, string $domain, string $content): ?array
    {
        $storageId = $this->addStorage($domain, $content);

        /**
         * @see https://developer.crowdin.com/api/v2/#operation/api.projects.files.put (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2/#operation/api.projects.files.put (Crowdin Enterprise API)
         */
        $response = $this->client->request('PUT', 'files/'.$fileId, [
            'json' => [
                'storageId' => $storageId,
            ],
        ]);

        if (200 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to update file in Crowdin for file ID "%d" and domain "%s": "%s".', $fileId, $domain, $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException(sprintf('Unable to update file in Crowdin for file ID "%d" and domain "%s".', $fileId, $domain), $response);
            }

            return null;
        }

        return $response->toArray()['data'];
    }

    private function uploadTranslations(int $fileId, string $domain, string $content, string $locale): ResponseInterface
    {
        $storageId = $this->addStorage($domain, $content);

        /*
         * @see https://developer.crowdin.com/api/v2/#operation/api.projects.translations.postOnLanguage (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2/#operation/api.projects.translations.postOnLanguage (Crowdin Enterprise API)
         */
        return $this->client->request('POST', 'translations/'.str_replace('_', '-', $locale), [
            'json' => [
                'storageId' => $storageId,
                'fileId' => $fileId,
            ],
        ]);
    }

    private function exportProjectTranslations(string $languageId, int $fileId): ResponseInterface
    {
        /*
         * @see https://developer.crowdin.com/api/v2/#operation/api.projects.translations.exports.post (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2/#operation/api.projects.translations.exports.post (Crowdin Enterprise API)
         */
        return $this->client->request('POST', 'translations/exports', [
            'json' => [
                'targetLanguageId' => str_replace('_', '-', $languageId),
                'fileIds' => [$fileId],
            ],
        ]);
    }

    private function downloadSourceFile(int $fileId): ResponseInterface
    {
        /*
         * @see https://developer.crowdin.com/api/v2/#operation/api.projects.files.download.get (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2/#operation/api.projects.files.download.get (Crowdin Enterprise API)
         */
        return $this->client->request('GET', sprintf('files/%d/download', $fileId));
    }

    private function listStrings(int $fileId, int $limit, int $offset): array
    {
        /**
         * @see https://developer.crowdin.com/api/v2/#operation/api.projects.strings.getMany (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2/#operation/api.projects.strings.getMany (Crowdin Enterprise API)
         */
        $response = $this->client->request('GET', 'strings', [
            'query' => [
                'fileId' => $fileId,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to list strings for file "%d".', $fileId), $response);
        }

        return $response->toArray()['data'];
    }

    private function deleteString(int $stringId): ResponseInterface
    {
        /*
         * @see https://developer.crowdin.com/api/v2/#operation/api.projects.strings.delete (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2#operation/api.projects.strings.delete (Crowdin Enterprise API)
         */
        return $this->client->request('DELETE', 'strings/'.$stringId);
    }

    private function addStorage(string $domain, string $content): int
    {
        /**
         * @see https://developer.crowdin.com/api/v2/#operation/api.storages.post (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2/#operation/api.storages.post (Crowdin Enterprise API)
         */
        $response = $this->client->request('POST', '../../storages', [
            'headers' => [
                'Crowdin-API-FileName' => urlencode(sprintf('%s.%s', $domain, 'xlf')),
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $content,
        ]);

        if (201 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add a Storage in Crowdin for domain "%s".', $domain), $response);
        }

        return $response->toArray()['data']['id'];
    }

    private function getFileList(): array
    {
        $result = [];

        /**
         * @see https://developer.crowdin.com/api/v2/#operation/api.projects.files.getMany (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2/#operation/api.projects.files.getMany (Crowdin Enterprise API)
         */
        $response = $this->client->request('GET', 'files');

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException('Unable to list Crowdin files.', $response);
        }

        $fileList = $response->toArray()['data'];

        foreach ($fileList as $file) {
            $result[$file['data']['name']] = $file['data']['id'];
        }

        return $result;
    }

    private function mapLocalesToLanguageId(array $locales): array
    {
        /**
         * We cannot query by locales, we need to fetch all and filter out the relevant ones.
         *
         * @see https://developer.crowdin.com/api/v2/#operation/api.languages.getMany (Crowdin API)
         * @see https://developer.crowdin.com/enterprise/api/v2/#operation/api.languages.getMany (Crowdin Enterprise API)
         */
        $response = $this->client->request('GET', '../../languages?limit=500');

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException('Unable to list set languages.', $response);
        }

        $localeLanguageMap = [];
        foreach ($response->toArray()['data'] as $language) {
            foreach (['locale', 'osxLocale', 'id'] as $key) {
                if (\in_array($language['data'][$key], $locales, true)) {
                    $localeLanguageMap[$language['data'][$key]] = $language['data']['id'];
                }
            }
        }

        if (\count($localeLanguageMap) !== \count($locales)) {
            $message = implode('", "', array_diff($locales, array_keys($localeLanguageMap)));
            $message = sprintf('Unable to find all requested locales: "%s" not found.', $message);
            $this->logger->error($message);

            throw new ProviderException($message, $response);
        }

        return $localeLanguageMap;
    }
}
