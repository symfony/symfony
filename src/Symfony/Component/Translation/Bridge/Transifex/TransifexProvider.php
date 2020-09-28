<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Transifex;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\AsciiSlugger;
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
 * In Transifex:
 * Resource refers to Symfony's translation keys;
 * Translations refers to Symfony's translated messages;
 * categories refers to Symfony's translation domains
 */
final class TransifexProvider extends AbstractProvider
{
    protected const HOST = 'www.transifex.com/api/2';

    private $projectSlug;
    private $apiKey;
    private $loader;
    private $logger;
    private $defaultLocale;
    private $slugger;

    public function __construct(string $projectSlug, string $apiKey, HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null, AsciiSlugger $slugger = null)
    {
        $this->projectSlug = $projectSlug;
        $this->apiKey = $apiKey;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->slugger = $slugger;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf('transifex://%s', $this->getEndpoint());
    }

    public function getName(): string
    {
        return 'transifex';
    }

    public function write(TranslatorBag $translatorBag, bool $override = false): void
    {
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            foreach ($catalogue->all() as $domain => $messages) {
                $this->ensureProjectExists($domain);
                $locale = $catalogue->getLocale();

                foreach ($messages as $id => $message) {
                    $this->createResource($id, $domain);
                    $this->createTranslation($id, $message, $locale, $domain);
                }
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
        }

        return $translatorBag;
    }

    public function delete(TranslatorBag $translations): void
    {
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode('api:'.$this->apiKey),
            'Content-Type' => 'application/json',
        ];
    }

    private function createResource(string $id, string $domain): void
    {
        $response = $this->client->request('GET', sprintf('https://%s/project/%s/resources/', $this->getEndpoint(), $this->getProjectSlug($domain)), [
            'headers' => $this->getDefaultHeaders(),
        ]);

        $resources = array_reduce(json_decode($response->getContent(), true), function ($carry, $resource) {
            $carry[] = $resource['name'];

            return $carry;
        }, []);

        if (\in_array($id, $resources)) {
            return;
        }

        $response = $this->client->request('POST', sprintf('https://%s/project/%s/resources/', $this->getEndpoint(), $this->getProjectSlug($domain)), [
            'headers' => $this->getDefaultHeaders(),
            'body' => json_encode([
                'slug' => $id,
                'name' => $id,
                'i18n_type' => 'TXT',
                'accept_translations' => true,
                'content' => $id,
            ]),
        ]);

        if (Response::HTTP_BAD_REQUEST === $response->getStatusCode()) {
            // Translation key already exists in Transifex.
            return;
        }

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add new translation key (%s) to Transifex: (status code: "%s") "%s".', $id, $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    private function createTranslation(string $id, string $message, string $locale, string $domain)
    {
        $response = $this->client->request('PUT', sprintf('https://%s/project/%s/resource/%s/translation/%s/', $this->getEndpoint(), $this->getProjectSlug($domain), $id, $locale), [
            'headers' => $this->getDefaultHeaders(),
            'body' => json_encode([
                'content' => $message,
            ]),
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to translate "%s : "%s"" in locale "%s" to Transifex: (status code: "%s") "%s".', $id, $message, $locale, $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    private function ensureProjectExists(string $domain): void
    {
        $projectName = $this->getProjectName($domain);

        $response = $this->client->request('GET', sprintf('https://%s/projects', $this->getEndpoint()), [
            'headers' => $this->getDefaultHeaders(),
        ]);

        $projectNames = array_reduce(json_decode($response->getContent(), true), function ($carry, $project) {
            $carry[] = $project['name'];

            return $carry;
        }, []);

        if (\in_array($projectName, $projectNames)) {
            return;
        }

        $response = $this->client->request('POST', sprintf('https://%s/projects', $this->getEndpoint()), [
            'headers' => $this->getDefaultHeaders(),
            'body' => json_encode([
                'name' => $projectName,
                'slug' => $this->getProjectSlug($domain),
                'description' => $domain.' translations domain',
                'source_language_code' => $this->defaultLocale,
                'repository_url' => 'http://github.com/php-translation/symfony', // @todo: only for test purpose, to remove
            ]),
        ]);

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add new project named "%s" to Transifex: (status code: "%s") "%s".', $projectName, $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    private function getProjectName(string $domain): string
    {
        return $this->projectSlug.'-'.$domain;
    }

    private function getProjectSlug(string $domain): string
    {
        return $this->slugger->slug($this->getProjectName($domain));
    }
}
