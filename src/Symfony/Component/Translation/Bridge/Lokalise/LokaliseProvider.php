<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Lokalise;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Exception\RuntimeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * In Lokalise:
 *  * Filenames refers to Symfony's translation domains;
 *  * Keys refers to Symfony's translation keys;
 *  * Translations refers to Symfony's translated messages
 */
final class LokaliseProvider implements ProviderInterface
{
    private const LOKALISE_GET_KEYS_LIMIT = 5000;
    private const PROJECT_TOO_BIG_STATUS_CODE = 413;
    private const FAILED_PROCESS_STATUS = ['cancelled', 'failed'];
    private const SUCESS_PROCESS_STATUS = 'finished';

    public function __construct(
        private HttpClientInterface $client,
        private LoaderInterface $loader,
        private LoggerInterface $logger,
        private string $defaultLocale,
        private string $endpoint,
    ) {
    }

    public function __toString(): string
    {
        return \sprintf('lokalise://%s', $this->endpoint);
    }

    /**
     * Lokalise API recommends sending payload in chunks of up to 500 keys per request.
     *
     * @see https://app.lokalise.com/api2docs/curl/#transition-create-keys-post
     */
    public function write(TranslatorBagInterface $translatorBag): void
    {
        $defaultCatalogue = $translatorBag->getCatalogue($this->defaultLocale);

        $this->ensureAllLocalesAreCreated($translatorBag);
        $existingKeysByDomain = [];

        foreach ($defaultCatalogue->getDomains() as $domain) {
            if (!\array_key_exists($domain, $existingKeysByDomain)) {
                $existingKeysByDomain[$domain] = [];
            }

            $existingKeysByDomain[$domain] += $this->getKeysIds([], $domain);
        }

        $keysToCreate = $createdKeysByDomain = [];

        foreach ($existingKeysByDomain as $domain => $existingKeys) {
            $allKeysForDomain = array_keys($defaultCatalogue->all($domain));
            foreach (array_keys($existingKeys) as $keyName) {
                unset($allKeysForDomain[$keyName]);
            }
            $keysToCreate[$domain] = $allKeysForDomain;
        }

        foreach ($keysToCreate as $domain => $keys) {
            $createdKeysByDomain[$domain] = $this->createKeys($keys, $domain);
        }

        $this->updateTranslations(array_merge_recursive($createdKeysByDomain, $existingKeysByDomain), $translatorBag);
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();
        $translations = $this->exportFiles($locales, $domains);

        foreach ($translations as $locale => $files) {
            foreach ($files as $filename => $content) {
                $translatorBag->addCatalogue($this->loader->load($content['content'], $locale, str_replace('.xliff', '', $filename)));
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        $catalogue = $translatorBag->getCatalogue($this->defaultLocale);

        $keysIds = [];

        foreach ($catalogue->getDomains() as $domain) {
            $keysToDelete = array_keys($catalogue->all($domain));

            if (!$keysToDelete) {
                continue;
            }

            $keysIds += $this->getKeysIds($keysToDelete, $domain);
        }

        if (!$keysIds) {
            return;
        }

        $response = $this->client->request('DELETE', 'keys', [
            'json' => ['keys' => array_values($keysIds)],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(\sprintf('Unable to delete keys from Lokalise: "%s".', $response->getContent(false)), $response);
        }
    }

    /**
     * @see https://app.lokalise.com/api2docs/curl/#transition-download-files-post
     */
    private function exportFiles(array $locales, array $domains): array
    {
        $response = $this->client->request('POST', 'files/export', [
            'json' => [
                'format' => 'symfony_xliff',
                'original_filenames' => true,
                'filter_langs' => array_values($locales),
                'filter_filenames' => array_map($this->getLokaliseFilenameFromDomain(...), $domains),
                'export_empty_as' => 'skip',
                'replace_breaks' => false,
            ],
        ]);

        $responseContent = $response->toArray(false);

        if (406 === $response->getStatusCode()
            && 'No keys found with specified filenames.' === $responseContent['error']['message']
        ) {
            return [];
        }

        if (200 !== $response->getStatusCode()) {
            if (self::PROJECT_TOO_BIG_STATUS_CODE !== ($responseContent['error']['code'] ?? null)) {
                throw new ProviderException(\sprintf('Unable to export translations from Lokalise: "%s".', $response->getContent(false)), $response);
            }
            if (!\extension_loaded('zip')) {
                throw new ProviderException(\sprintf('Unable to export translations from Lokalise: "%s". Make sure that the "zip" extension is enabled.', $response->getContent(false)), $response);
            }

            return $this->exportFilesAsync($locales, $domains);
        }

        // Lokalise returns languages with "-" separator, we need to reformat them to "_" separator.
        $reformattedLanguages = array_map(function ($language) {
            return str_replace('-', '_', $language);
        }, array_keys($responseContent['files']));

        return array_combine($reformattedLanguages, $responseContent['files']);
    }

    /**
     * @see https://developers.lokalise.com/reference/download-files-async
     */
    private function exportFilesAsync(array $locales, array $domains): array
    {
        $response = $this->client->request('POST', 'files/async-download', [
            'json' => [
                'format' => 'symfony_xliff',
                'original_filenames' => true,
                'filter_langs' => array_values($locales),
                'filter_filenames' => array_map($this->getLokaliseFilenameFromDomain(...), $domains),
                'export_empty_as' => 'skip',
                'replace_breaks' => false,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(\sprintf('Unable to export translations from Lokalise: "%s".', $response->getContent(false)), $response);
        }

        $processId = $response->toArray()['process_id'];
        while (true) {
            $response = $this->client->request('GET', \sprintf('processes/%s', $processId));
            $process = $response->toArray()['process'];
            if (\in_array($process['status'], self::FAILED_PROCESS_STATUS, true)) {
                throw new ProviderException(\sprintf('Unable to export translations from Lokalise: "%s".', $response->getContent(false)), $response);
            }
            if (self::SUCESS_PROCESS_STATUS === $process['status']) {
                $downloadUrl = $process['details']['download_url'];
                break;
            }
            usleep(500000);
        }

        $response = $this->client->request('GET', $downloadUrl, ['buffer' => false]);
        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(\sprintf('Unable to download translations file from Lokalise: "%s".', $response->getContent(false)), $response);
        }
        $zipFile = tempnam(sys_get_temp_dir(), 'lokalise');
        $extractPath = $zipFile.'.dir';
        try {
            if (!$h = @fopen($zipFile, 'w')) {
                throw new RuntimeException(error_get_last()['message'] ?? 'Failed to create temporary file.');
            }
            foreach ($this->client->stream($response) as $chunk) {
                fwrite($h, $chunk->getContent());
            }
            fclose($h);

            $zip = new \ZipArchive();
            if (!$zip->open($zipFile)) {
                throw new RuntimeException('Failed to open zipped translations from Lokalise.');
            }

            try {
                if (!$zip->extractTo($extractPath)) {
                    throw new RuntimeException('Failed to unzip translations from Lokalize.');
                }
            } finally {
                $zip->close();
            }

            return $this->getZipContents($extractPath);
        } finally {
            if (is_resource($h)) {
                fclose($h);
            }
            @unlink($zipFile);
            $this->removeDir($extractPath);
        }
    }

    private function getZipContents(string $dir): array
    {
        $contents = [];
        foreach (scandir($dir) as $lang) {
            if (\in_array($lang, ['.', '..'], true)) {
                continue;
            }
            $path = $dir.'/'.$lang;
            // Lokalise returns languages with "-" separator, we need to reformat them to "_" separator.
            $lang = str_replace('-', '_', $lang);
            foreach (scandir($path) as $name) {
                if (!\in_array($name, ['.', '..'], true)) {
                    $contents[$lang][$name]['content'] = file_get_contents($path.'/'.$name);
                }
            }
        }

        return $contents;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }

    private function createKeys(array $keys, string $domain): array
    {
        $keysToCreate = [];

        foreach ($keys as $key) {
            $keysToCreate[] = [
                'key_name' => $key,
                'platforms' => ['web'],
                'filenames' => [
                    'web' => $this->getLokaliseFilenameFromDomain($domain),
                    // There is a bug in Lokalise with "Per platform key names" option enabled,
                    // we need to provide a filename for all platforms.
                    'ios' => null,
                    'android' => null,
                    'other' => null,
                ],
            ];
        }

        $chunks = array_chunk($keysToCreate, 500);
        $responses = [];

        foreach ($chunks as $chunk) {
            $responses[] = $this->client->request('POST', 'keys', [
                'json' => ['keys' => $chunk],
            ]);
        }

        $createdKeys = [];

        foreach ($responses as $response) {
            if (200 !== $statusCode = $response->getStatusCode()) {
                $this->logger->error(\sprintf('Unable to create keys to Lokalise: "%s".', $response->getContent(false)));

                if (500 <= $statusCode) {
                    throw new ProviderException('Unable to create keys to Lokalise.', $response);
                }

                continue;
            }

            $keys = $response->toArray(false)['keys'] ?? [];
            $createdKeys = array_reduce($keys, static function ($carry, array $keyItem) {
                $carry[$keyItem['key_name']['web']] = $keyItem['key_id'];

                return $carry;
            }, $createdKeys);
        }

        return $createdKeys;
    }

    /**
     * Translations will be created for keys without existing translations.
     * Translations will be updated for keys with existing translations.
     */
    private function updateTranslations(array $keysByDomain, TranslatorBagInterface $translatorBag): void
    {
        $keysToUpdate = [];

        foreach ($keysByDomain as $domain => $keys) {
            foreach ($keys as $keyName => $keyId) {
                $keysToUpdate[] = [
                    'key_id' => $keyId,
                    'platforms' => ['web'],
                    'filenames' => [
                        'web' => $this->getLokaliseFilenameFromDomain($domain),
                        'ios' => null,
                        'android' => null,
                        'other' => null,
                    ],
                    'translations' => array_reduce($translatorBag->getCatalogues(), static function ($carry, MessageCatalogueInterface $catalogue) use ($keyName, $domain) {
                        // Message could be not found because the catalogue is empty.
                        // We must not send the key in place of the message to avoid wrong message update on the provider.
                        if ($catalogue->get($keyName, $domain) !== $keyName) {
                            $carry[] = [
                                'language_iso' => $catalogue->getLocale(),
                                'translation' => $catalogue->get($keyName, $domain),
                            ];
                        }

                        return $carry;
                    }, []),
                ];
            }
        }

        if (!$keysToUpdate) {
            return;
        }

        $response = $this->client->request('PUT', 'keys', [
            'json' => ['keys' => $keysToUpdate],
        ]);

        if (200 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(\sprintf('Unable to create/update translations to Lokalise: "%s".', $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException('Unable to create/update translations to Lokalise.', $response);
            }
        }
    }

    private function getKeysIds(array $keys, string $domain, int $page = 1): array
    {
        $response = $this->client->request('GET', 'keys', [
            'query' => [
                'filter_keys' => implode(',', $keys),
                'filter_filenames' => $this->getLokaliseFilenameFromDomain($domain),
                'limit' => self::LOKALISE_GET_KEYS_LIMIT,
                'page' => $page,
            ],
        ]);

        if (200 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(\sprintf('Unable to get keys ids from Lokalise: "%s".', $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException('Unable to get keys ids from Lokalise.', $response);
            }
        }

        $result = [];
        $keysFromResponse = $response->toArray(false)['keys'] ?? [];

        if (\count($keysFromResponse) > 0) {
            $result = array_reduce($keysFromResponse, static function ($carry, array $keyItem) {
                $carry[$keyItem['key_name']['web']] = $keyItem['key_id'];

                return $carry;
            }, []);
        }

        $paginationTotalCount = $response->getHeaders(false)['x-pagination-total-count'] ?? [];
        $keysTotalCount = (int) (reset($paginationTotalCount) ?? 0);

        if (0 === $keysTotalCount) {
            return $result;
        }

        $pages = ceil($keysTotalCount / self::LOKALISE_GET_KEYS_LIMIT);
        if ($page < $pages) {
            $result = array_merge($result, $this->getKeysIds($keys, $domain, ++$page));
        }

        return $result;
    }

    private function ensureAllLocalesAreCreated(TranslatorBagInterface $translatorBag): void
    {
        $providerLanguages = $this->getLanguages();
        $missingLanguages = array_reduce($translatorBag->getCatalogues(), static function ($carry, $catalogue) use ($providerLanguages) {
            if (!\in_array($catalogue->getLocale(), $providerLanguages, true)) {
                $carry[] = $catalogue->getLocale();
            }

            return $carry;
        }, []);

        if ($missingLanguages) {
            $this->createLanguages($missingLanguages);
        }
    }

    private function getLanguages(): array
    {
        $response = $this->client->request('GET', 'languages');

        if (200 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(\sprintf('Unable to get languages from Lokalise: "%s".', $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException('Unable to get languages from Lokalise.', $response);
            }

            return [];
        }

        $responseContent = $response->toArray(false);

        if (\array_key_exists('languages', $responseContent)) {
            return array_column($responseContent['languages'], 'lang_iso');
        }

        return [];
    }

    private function createLanguages(array $languages): void
    {
        $response = $this->client->request('POST', 'languages', [
            'json' => [
                'languages' => array_map(static fn ($language) => ['lang_iso' => $language], $languages),
            ],
        ]);

        if (200 !== $statusCode = $response->getStatusCode()) {
            $this->logger->error(\sprintf('Unable to create languages on Lokalise: "%s".', $response->getContent(false)));

            if (500 <= $statusCode) {
                throw new ProviderException('Unable to create languages on Lokalise.', $response);
            }
        }
    }

    private function getLokaliseFilenameFromDomain(string $domain): string
    {
        return \sprintf('%s.xliff', $domain);
    }
}
