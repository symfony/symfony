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
use Symfony\Component\Translation\CatalogueMetadataAwareInterface;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
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
 */
final class LocoProvider implements ProviderInterface
{
    private string $endpoint;
    private ?TranslatorBagInterface $translatorBag;
    private ?string $restrictToStatus;
    private XliffFileDumper $dumper;

    public function __construct(
        private HttpClientInterface $client,
        private LoaderInterface $loader,
        private LoggerInterface $logger,
        string $endpoint,
        TranslatorBagInterface|string|null $translatorBag = null,
        TranslatorBagInterface|string|null $restrictToStatus = null,
        XliffFileDumper|string|null $dumper = null,
    ) {
        if (\is_string($translatorBag)) {
            trigger_deprecation('symfony/loco-translation-provider', '8.2', '"%s" constructor "$defaultLocale" parameter has no effect and will be removed in version 9.0.', __CLASS__);

            $this->endpoint = $translatorBag;
            $this->translatorBag = $restrictToStatus;
            $this->restrictToStatus = $dumper;
            $dumper = \func_get_args()[7] ?? null;
        } else {
            $this->endpoint = $endpoint;
            $this->translatorBag = $translatorBag;
            $this->restrictToStatus = $restrictToStatus;
        }

        $this->dumper = $dumper ?? new XliffFileDumper();
    }

    public function __toString(): string
    {
        if ($this->restrictToStatus) {
            return \sprintf('loco://%s?status=%s', $this->endpoint, $this->restrictToStatus);
        }

        return \sprintf('loco://%s', $this->endpoint);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $this->createMissingLocales($translatorBag);

        $responses = [];

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locale = $catalogue->getLocale();

            foreach ($catalogue->all() as $domain => $messages) {
                $reindexedCatalog = new MessageCatalogue($locale);

                foreach ($messages as $key => $message) {
                    $reindexedCatalog->set("{$domain}__{$key}", $message, $domain);
                }

                $responses[] = $this->client->request('POST', 'import/xlf', [
                    'body' => $this->dumper->formatCatalogue($reindexedCatalog, $domain, ['default_locale' => $locale]),
                    'query' => [
                        'locale' => $locale,
                        'tag-new' => $domain,
                    ],
                    'user_data' => [
                        'locale' => $locale,
                        'domain' => $domain,
                    ],
                ]);
            }
        }

        foreach ($this->client->stream($responses) as $response => $chunk) {
            if ($chunk->isFirst() && 200 === $response->getStatusCode()) {
                $response->cancel();
            }
            if (!$chunk->isLast()) {
                continue;
            }

            ['locale' => $locale, 'domain' => $domain] = $response->getInfo('user_data');

            $this->logger->error(\sprintf('Unable to import domain "%s" for locale "%s" to Loco: "%s".', $domain, $locale, $response->getContent(false)));

            if (500 <= $response->getStatusCode()) {
                throw new ProviderException(\sprintf('Unable to import domain "%s" for locale "%s" to Loco.', $domain, $locale), $response);
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        if (!$domains || ['*'] === $domains) {
            trigger_deprecation('symfony/loco-translation-provider', '8.2', 'Passing no domains or "*" to "%s" is deprecated, configure your loco provider domains as an associative array with an empty string key and "*" as value.', __METHOD__);

            $domains = ['' => '*'];
        } else {
            $filters = [];
            foreach ($domains as $filter => $domain) {
                $filters[\is_int($filter) ? $domain : $filter] = $domain;
            }
            $domains = $filters;
        }
        $locales = $locales ?: $this->getLocales();

        $translatorBag = new TranslatorBag();
        $responses = [];

        foreach ($locales as $locale) {
            $previousCatalogue = $this->translatorBag?->getCatalogue($locale);

            foreach ($domains as $filter => $domain) {
                $responses[] = $this->client->request('GET', \sprintf('export/locale/%s.xlf', rawurlencode($locale)), [
                    'query' => [
                        'filter' => $filter,
                        'status' => $this->restrictToStatus ?? 'translated,blank-translation',
                    ],
                    'headers' => [
                        'If-Modified-Since' => $previousCatalogue instanceof CatalogueMetadataAwareInterface ? $previousCatalogue->getCatalogueMetadata('last-modified', $domain) : null,
                    ],
                    'user_data' => [
                        'locale' => $locale,
                        'filter' => $filter,
                        'domain' => $domain,
                        'previousCatalogue' => $previousCatalogue,
                    ],
                ]);
            }
        }

        foreach ($this->client->stream($responses) as $response => $chunk) {
            if ($response->getInfo('canceled')) {
                continue;
            }

            ['locale' => $locale, 'filter' => $filter, 'domain' => $domain, 'previousCatalogue' => $previousCatalogue] = $response->getInfo('user_data');

            if ($chunk->isFirst()) {
                if (404 === $response->getStatusCode()) {
                    $this->logger->warning(\sprintf('Locale "%s" does not exist in your Loco project.', $locale));

                    foreach ($responses as $localeResponse) {
                        if ($locale === $localeResponse->getInfo('user_data')['locale']) {
                            $localeResponse->cancel();
                        }
                    }

                    continue;
                }

                if (304 === $response->getStatusCode()) {
                    $this->logger->info(\sprintf('No modifications found in Loco for locale "%s" and domain "%s".', $locale, $domain));

                    $catalogue = new MessageCatalogue($locale);
                    $previousMessages = $previousCatalogue->all($domain);

                    if (!str_ends_with($domain, $catalogue::INTL_DOMAIN_SUFFIX)) {
                        $previousMessages = array_diff_key($previousMessages, $previousCatalogue->all($domain.$catalogue::INTL_DOMAIN_SUFFIX));
                    }
                    foreach ($previousMessages as $key => $message) {
                        $catalogue->set($this->retrieveKeyFromId($key, $domain), $message, $domain);
                    }

                    foreach ($previousCatalogue->getCatalogueMetadata('', $domain) as $key => $value) {
                        $catalogue->setCatalogueMetadata($key, $value, $domain);
                    }

                    $translatorBag->addCatalogue($catalogue);

                    $response->cancel();
                }
            } elseif ($chunk->isLast()) {
                $responseContent = $response->getContent(false);

                if (200 !== $response->getStatusCode()) {
                    throw new ProviderException('Unable to read the Loco response: '.$responseContent, $response);
                }

                $locoCatalogue = $this->loader->load($responseContent, $locale, $domain);
                $catalogue = new MessageCatalogue($locale);

                foreach ($locoCatalogue->all($domain) as $key => $message) {
                    $catalogue->set($this->retrieveKeyFromId($key, $filter), $message, $domain);
                }

                if ($previousCatalogue instanceof CatalogueMetadataAwareInterface) {
                    foreach ($previousCatalogue->getCatalogueMetadata('', $domain) ?? [] as $key => $value) {
                        $catalogue->setCatalogueMetadata($key, $value, $domain);
                    }
                }

                if (null !== $lastModified = $response->getHeaders()['last-modified'][0] ?? null) {
                    $catalogue->setCatalogueMetadata('last-modified', $lastModified, $domain);
                }

                $translatorBag->addCatalogue($catalogue);
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        $responses = new \SplObjectStorage();
        $deletedIds = [];

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            foreach ($catalogue->all() as $domain => $messages) {
                foreach ($messages as $key => $message) {
                    $id = $domain.'__'.$key;
                    if (isset($deletedIds[$id])) {
                        continue;
                    }

                    $responses[$this->client->request('DELETE', \sprintf('assets/%s.json', rawurlencode($id)))] = $id;
                    $deletedIds[$id] = $id;
                }
            }
        }

        foreach ($this->client->stream($responses) as $response => $chunk) {
            if ($chunk->isFirst()) {
                if (403 === $statusCode = $response->getStatusCode()) {
                    $assetId = $responses[$response];
                    $this->logger->error(\sprintf('The API key used does not have sufficient permissions to delete asset "%s".', $assetId));
                    $response->cancel();

                    throw new ProviderException(\sprintf('Unable to delete translation key "%s" to Loco: forbidden.', $assetId), $response);
                }
                if (200 === $statusCode || 404 === $statusCode) {
                    $response->cancel();
                }
            } elseif ($chunk->isLast()) {
                $assetId = $responses[$response];

                $this->logger->error(\sprintf('Unable to delete translation key "%s" to Loco: "%s".', $assetId, $response->getContent(false)));

                if (500 <= $response->getStatusCode()) {
                    throw new ProviderException(\sprintf('Unable to delete translation key "%s" to Loco.', $assetId), $response);
                }
            }
        }
    }

    private function createMissingLocales(TranslatorBagInterface $translatorBag): void
    {
        $locales = [];
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locales[] = $catalogue->getLocale();
        }

        if (!$missingLocales = array_diff($locales, $this->getLocales())) {
            return;
        }

        $responses = [];

        foreach (array_unique($missingLocales) as $locale) {
            $responses[] = $this->client->request('POST', 'locales', [
                'body' => ['code' => $locale],
                'user_data' => $locale,
            ]);
        }

        foreach ($this->client->stream($responses) as $response => $chunk) {
            if (201 !== $response->getStatusCode()) {
                throw new ProviderException(\sprintf('Unable to create locale "%s" on Loco.', $response->getInfo('user_data')), $response);
            }

            $response->cancel();
        }
    }

    private function getLocales(): array
    {
        $response = $this->client->request('GET', 'locales');

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException('Unable to get locales on Loco.', $response);
        }

        $locales = [];
        foreach ($response->toArray() as $locale) {
            $locales[] = str_replace('-', '_', $locale['code']);
        }

        return $locales;
    }

    private function retrieveKeyFromId(string $id, string $domain): string
    {
        if (str_starts_with($id, $domain.'__')) {
            return substr($id, \strlen($domain) + 2);
        }

        return $id;
    }
}
