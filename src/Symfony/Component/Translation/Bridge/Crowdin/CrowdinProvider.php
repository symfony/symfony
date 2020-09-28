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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\AbstractProvider;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 */
final class CrowdinProvider extends AbstractProvider
{
    protected const HOST = 'crowdin.com/api/v2';

    private $projectId;
    private $token;
    private $loader;
    private $logger;
    private $defaultLocale;
    private $xliffFileDumper;
    private $files = [];

    public function __construct(string $projectId, string $token, HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null, XliffFileDumper $xliffFileDumper = null)
    {
        $this->projectId = $projectId;
        $this->token = $token;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->xliffFileDumper = $xliffFileDumper;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf('crowdin://%s', $this->getEndpoint());
    }

    public function getName(): string
    {
        return 'crowdin';
    }

    public function write(TranslatorBag $translations, bool $override = false): void
    {
        foreach($translations->getDomains() as $domain) {
            foreach ($translations->getCatalogues() as $catalogue) {
                $content = $this->xliffFileDumper->formatCatalogue($catalogue, $domain);
                $fileId = $this->getFileId($domain);

                if ($catalogue->getLocale() === $this->defaultLocale) {
                    if (!$fileId) {
                        $this->addFile($domain, $content);
                    } else {
                        $this->updateFile($fileId, $domain, $content);
                    }
                } else {
                    $this->uploadTranslations($fileId, $domain, $content, $catalogue->getLocale());
                }
            }
        }
    }

    /**
     * @see https://support.crowdin.com/api/v2/#operation/api.projects.translations.exports.post
     */
    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            // TODO: Implement read() method.
        }

        return $translatorBag;
    }

    public function delete(TranslatorBag $translations): void
    {
        // TODO: Implement delete() method.
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    private function getFileId(string $domain): ?int
    {
        if (isset($this->files[$domain])) {
            return $this->files[$domain];
        }

        try {
            $files = $this->getFilesList();
        } catch (ProviderException $e) {
            return null;
        }

        foreach($files as $file) {
            if ($file['data']['name'] === sprintf('%s.%s', $domain, 'xlf')) {
                return $this->files[$domain] = (int) $file['data']['id'];
            }
        }

        return null;
    }

    /**
     * @see https://support.crowdin.com/api/v2/#operation/api.projects.files.post
     */
    private function addFile(string $domain, string $content): void
    {
        $storageId = $this->addStorage($domain, $content);
        $response = $this->client->request('POST', sprintf('https://%s/projects/%s/files', $this->getEndpoint(), $this->projectId), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ]),
            'body' => json_encode([
                'storageId' => $storageId,
                'name' => sprintf('%s.%s', $domain, 'xlf'),
            ]),
        ]);

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add a File in Crowdin for domain "%s".', $domain), $response);
        }

        $this->files[$domain] = (int) json_decode($response->getContent(), true)['data']['id'];
    }

    /**
     * @see https://support.crowdin.com/api/v2/#operation/api.projects.files.put
     */
    private function updateFile(int $fileId, string $domain, string $content): void
    {
        $storageId = $this->addStorage($domain, $content);
        $response = $this->client->request('PUT', sprintf('https://%s/projects/%s/files/%d', $this->getEndpoint(), $this->projectId, $fileId), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ]),
            'body' => json_encode([
                'storageId' => $storageId,
            ]),
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to update file in Crowdin for file ID "%d" and domain "%s".', $fileId, $domain),
                $response
            );
        }
    }

    /**
     * @see https://support.crowdin.com/api/v2/#operation/api.projects.translations.postOnLanguage
     */
    private function uploadTranslations(?int $fileId, string $domain, string $content, string $locale): void
    {
        if (!$fileId) {
            return;
        }

        $storageId = $this->addStorage($domain, $content);
        $response = $this->client->request('POST', sprintf('https://%s/projects/%s/translations/%s', $this->getEndpoint(), $this->projectId, $locale), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ]),
            'body' => json_encode([
                'storageId' => $storageId,
                'fileId' => $fileId,
            ]),
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to upload translations to Crowdin for domain "%s" and locale "%s".', $domain, $locale),
                $response
            );
        }
    }

    /**
     * @see https://support.crowdin.com/api/v2/#operation/api.storages.post
     */
    private function addStorage(string $domain, string $content): int
    {
        $response = $this->client->request('POST', sprintf('https://%s/storages', $this->getEndpoint()), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Crowdin-API-FileName' => urlencode(sprintf('%s.%s', $domain, 'xlf')),
                'Content-Type' => 'application/octet-stream',
            ]),
            'body' => $content,
        ]);

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add a Storage in Crowdin for domain "%s".', $domain), $response);
        }

        $storage = json_decode($response->getContent(), true);

        return $storage['data']['id'];
    }

    /**
     * @see https://support.crowdin.com/api/v2/#operation/api.projects.files.getMany
     */
    private function getFilesList(): array
    {
        $response = $this->client->request('GET', sprintf('https://%s/projects/%d/files', $this->getEndpoint(), $this->projectId), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ]),
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException('Unable to list Crowdin files.', $response);
        }

        $files = json_decode($response->getContent(), true)['data'];

        if (count($files) === 0) {
            throw new ProviderException('Crowdin files list is empty.', $response);
        }

        return $files;
    }
}
