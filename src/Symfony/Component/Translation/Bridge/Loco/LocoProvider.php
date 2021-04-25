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
use Symfony\Component\Translaton\Provider\AbstractProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * In Loco:
 *  * Tags refers to Symfony's translation domains
 *  * Assets refers to Symfony's translation keys
 *  * Translations refers to Symfony's translated messages
 *
 * @experimental in 5.3
 */
final class LocoProvider extends AbstractProvider
{
    protected const HOST = 'localise.biz';

    private $defaultLocale;
    private $loader;

    public function __construct(string $apiKey, string $defaultLocale, LoaderInterface $loader, HttpClientInterface $client = null, LoggerInterface $logger = null)
    {
        parent::__construct($client, $logger);

        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;

        $endpoint = 'https://'.$this->getEndpoint().'/api/';

        $this->client->withOptions([
            'base_uri' => $endpoint,
            'headers' => [
                'Authorization' => 'Loco '.$apiKey,
            ],
        ]);

    }

    public function __toString(): string
    {
        return sprintf('loco://%s', $this->getEndpoint());
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $catalogue = $translatorBag->getCatalogue($this->defaultLocale);

        if (!$catalogue) {
            $catalogue = $translatorBag->getCatalogues()[0];
        }

        // Create keys on Loco
        foreach ($catalogue->all() as $domain => $messages) {
            $ids = [];
            foreach ($messages as $id => $message) {
                $ids[] = $id;
                $this->createAsset($id);
            }
            if ($ids) {
                $this->tagsAssets($ids, $domain);
            }
        }

        // Push translations in all locales and tag them with domain
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locale = $catalogue->getLocale();

            if (!\in_array($locale, $this->getLocales())) {
                $this->createLocale($locale);
            }

            foreach ($catalogue->all() as $messages) {
                foreach ($messages as $id => $message) {
                    $this->translateAsset($id, $message, $locale);
                }
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $domains = $domains ?: ['*'];
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $response = $this->client->request('GET', sprintf('export/locale/%s.xlf?filter=%s&status=translated', $locale, $domain));

                if (404 === $response->getStatusCode()) {
                    $this->logger->error(sprintf('Locale "%s" for domain "%s" does not exist in Loco.', $locale, $domain));
                    continue;
                }

                $responseContent = $response->getContent(false);

                if (200 !== $response->getStatusCode()) {
                    throw new ProviderException('Unable to read the Loco response: '.$responseContent, $response);
                }

                $translatorBag->addCatalogue($this->loader->load($responseContent, $locale, $domain));
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        $deletedIds = [];

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            foreach ($catalogue->all() as $messages) {
                foreach ($messages as $id => $message) {
                    if (\in_array($id, $deletedIds, true)) {
                        continue;
                    }

                    $this->deleteAsset($id);
                    $deletedIds[] = $id;
                }
            }
        }
    }

    private function createAsset(string $id): void
    {
        $response = $this->client->request('POST', 'assets', [
            'body' => [
                'name' => $id,
                'id' => $id,
                'type' => 'text',
                'default' => 'untranslated',
            ],
        ]);

        if (409 === $response->getStatusCode()) {
            $this->logger->info(sprintf('Translation key "%s" already exists in Loco.', $id), [
                'id' => $id,
            ]);
        } elseif (201 !== $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to add new translation key "%s" to Loco: (status code: "%s") "%s".', $id, $response->getStatusCode(), $response->getContent(false)));
        }
    }

    private function translateAsset(string $id, string $message, string $locale): void
    {
        $response = $this->client->request('POST', sprintf('translations/%s/%s', $id, $locale), [
            'body' => $message,
        ]);

        if (200 !== $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to add translation message "%s" (for key: "%s" in locale "%s") to Loco: "%s".', $message, $id, $locale, $response->getContent(false)));
        }
    }

    private function tagsAssets(array $ids, string $tag): void
    {
        $idsAsString = implode(',', array_unique($ids));

        if (!\in_array($tag, $this->getTags(), true)) {
            $this->createTag($tag);
        }

        $response = $this->client->request('POST', sprintf('tags/%s.json', $tag), [
            'body' => $idsAsString,
        ]);

        if (200 !== $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to add tag "%s" on translation keys "%s" to Loco: "%s".', $tag, $idsAsString, $response->getContent(false)));
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

    private function deleteAsset(string $id): void
    {
        $response = $this->client->request('DELETE', sprintf('assets/%s.json', $id));

        if (200 !== $response->getStatusCode()) {
            $this->logger->error(sprintf('Unable to delete translation key "%s" to Loco: "%s".', $id, $response->getContent(false)));
        }
    }
}
