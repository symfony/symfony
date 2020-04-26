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
use Symfony\Component\Translation\Loader\XliffRawLoader;
use Symfony\Component\Translation\Message\MessageInterface;
use Symfony\Component\Translation\Message\SmsMessage;
use Symfony\Component\Translation\Remote\AbstractRemote;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
final class LocoRemote extends AbstractRemote
{
    protected const HOST = 'localise.biz';

    private $apiKey;
    private $loader;

    public function __construct(string $apiKey, HttpClientInterface $client = null, LoaderInterface $loader)
    {
        $this->apiKey = $apiKey;
        $this->loader = $loader;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf('loco://%s', $this->getEndpoint());
    }

    /**
     * {@inheritdoc}
     */
    public function write(TranslatorBag $translations): void
    {
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
    }
}
