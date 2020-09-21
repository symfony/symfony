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
use Symfony\Component\Translation\Exception\TransportException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Remote\AbstractRemote;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 *
 * In Crowdin:
 */
final class CrowdinRemote extends AbstractRemote
{
    protected const HOST = 'api.crowdin.com';

    private $apiKey;
    private $loader;
    private $logger;
    private $defaultLocale;

    public function __construct(string $apiKey, HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->apiKey = $apiKey;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf('crowdin://%s', $this->getEndpoint());
    }

    public function write(TranslatorBag $translations, bool $override = false): void
    {
        // TODO: Implement write() method.
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $filter = $domains ? implode(',', $domains) : '*';
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            $response = $this->client->request('GET', sprintf('https://%s/api/export/locale/%s.xlf?filter=%s', $this->getEndpoint(), $locale, $filter), [
                'headers' => $this->getDefaultHeaders(),
            ]);

            $responseContent = $response->getContent(false);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new TransportException('Unable to read the Loco response: '.$responseContent, $response);
            }

            foreach ($domains as $domain) {
                $translatorBag->addCatalogue($this->loader->load($responseContent, $locale, $domain));
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBag $translations): void
    {
        // TODO: Implement delete() method.
    }
}
