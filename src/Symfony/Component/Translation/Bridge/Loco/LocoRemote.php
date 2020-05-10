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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Exception\RemoteException;
use Symfony\Component\Translation\Exception\TransportException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Message\MessageInterface;
use Symfony\Component\Translation\Message\SmsMessage;
use Symfony\Component\Translation\Remote\AbstractRemote;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 *
 * In Loco:
 * tags refers to Symfony's translation domains
 * assets refers to Symfony's translation keys
 * translations refers to Symfony's translation messages
 */
final class LocoRemote extends AbstractRemote
{
    protected const HOST = 'localise.biz';

    private $apiKey;
    private $loader;
    private $defaultLocale;

    public function __construct(string $apiKey, HttpClientInterface $client = null, LoaderInterface $loader = null, string $defaultLocale = null)
    {
        $this->apiKey = $apiKey;
        $this->loader = $loader;
        $this->defaultLocale = $defaultLocale;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf('loco://%s', $this->getEndpoint());
    }

    /**
     * {@inheritdoc}
     */
    public function write(TranslatorBag $translations, bool $override = false): void
    {
        foreach ($translations->all() as $locale => $messages) {
            foreach ($messages as $domain => $messages) {
                $ids = [];

                foreach ($messages as $id => $message) {
                    $ids[] = $id;
                    $this->createAsset($id);
                    $this->translateAsset($id, $message, $locale);
                }

                $this->tagsAssets($ids, $domain);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read(array $domains, array $locales): TranslatorBag
    {
        $filter = $domains ? implode(',', $domains) : '*';

        if (1 === count($locales)) {
            $response = $this->client->request('GET', sprintf('https://%s/api/export/locale/%s.xlf?filter=%s', $this->getEndpoint(), $locales[0], $filter), [
                'headers' => [
                    'Authorization' => 'Loco '.$this->apiKey,
                ],
            ]);
        } else {
            $response = $this->client->request('GET', sprintf('https://%s/api/export/all.xlf?filter=%s', $this->getEndpoint(), $filter), [
                'headers' => [
                    'Authorization' => 'Loco '.$this->apiKey,
                ],
            ]);
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new TransportException('Unable to read the Loco response: '.$response->getContent(false), $response);
        }

        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            if (\count($domains) > 1) {
                foreach ($domains as $domain) {
                    $translatorBag->addCatalogue($this->loader->load($response->getContent(), $locale, $domain));
                }
            } else {
                $translatorBag->addCatalogue($this->loader->load($response->getContent(), $locale, $domains[0] ?? 'messages')); // not sure
            }
        }

        return $translatorBag;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(TranslatorBag $translations): void
    {
        foreach ($translations->all() as $locale => $messages) {
            foreach ($messages as $domain => $messages) {
                foreach ($messages as $id => $message) {
                    $this->deleteAsset($id);
                }
            }
        }
    }

    private function createAsset(string $id)
    {
        $response = $this->client->request('POST', sprintf('https://%s/api/assets', $this->getEndpoint()), [
            'headers' => [
                'Authorization' => 'Loco '.$this->apiKey,
            ],
            'body' => [
                'name' => $id,
                'id' => $id,
                'type' => 'text',
                'default' => 'untranslated',
            ]
        ]);

        if ($response->getStatusCode() === Response::HTTP_CONFLICT) {
            // Translation key already exists in Loco, do nothing
        } elseif ($response->getStatusCode() !== Response::HTTP_CREATED || $response->getStatusCode() !== Response::HTTP_OK) {
            throw new TransportException(sprintf('Unable to add new translation key (%s) to Loco: %s', $id, $response->getContent(false)), $response);
        }
    }

    private function translateAsset(string $id, string $message, string $locale)
    {
        $response = $this->client->request('POST', sprintf('https://%s/api/translations/%s/%s', $this->getEndpoint(), $id, $locale), [
            'headers' => [
                'Authorization' => 'Loco '.$this->apiKey,
            ],
            'body' => $message,
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new TransportException(sprintf('Unable to add translation message (for key: %s) to Loco: %s', $id, $response->getContent(false)), $response);
        }
    }

    private function tagsAssets(array $ids, string $tag)
    {
        $idsAsString = implode(',', array_unique($ids));

        if (!\in_array($tag, $this->getTags())) {
            $this->createTag($tag);
        }

        $response = $this->client->request('POST', sprintf('https://%s/api/tags/%s.json', $this->getEndpoint(), $tag), [
            'headers' => [
                'Authorization' => 'Loco '.$this->apiKey,
            ],
            'body' => $idsAsString,
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new TransportException(sprintf('Unable to add tag (%s) on translation keys (%s) to Loco: %s', $tag, $idsAsString, $response->getContent(false)), $response);
        }
    }

    private function createTag(string $tag)
    {
        $response = $this->client->request('POST', sprintf('https://%s/api/tags.json', $this->getEndpoint(), $tag), [
            'headers' => [
                'Authorization' => 'Loco '.$this->apiKey,
            ],
            'name' => $tag,
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new TransportException(sprintf('Unable to create tag (%s) on Loco: %s', $tag, $response->getContent(false)), $response);
        }
    }

    private function getTags(): array
    {
        $response = $this->client->request('GET', sprintf('https://%s/api/tags.json', $this->getEndpoint()), [
            'headers' => [
                'Authorization' => 'Loco '.$this->apiKey,
            ],
        ]);

        $content = $response->getContent();

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new TransportException(sprintf('Unable to get tags on Loco: %s', $response->getContent(false)), $response);
        }

        return json_decode($content);
    }

    private function deleteAsset(string $id)
    {
        $response = $this->client->request('DELETE', sprintf('https://%s/api/assets/%s.json', $this->getEndpoint(), $id), [
            'headers' => [
                'Authorization' => 'Loco '.$this->apiKey,
            ]
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new TransportException(sprintf('Unable to add new translation key (%s) to Loco: %s', $id, $response->getContent(false)), $response);
        }
    }
}
