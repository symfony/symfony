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
use Symfony\Component\Translation\Provider\AbstractProvider;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 *
 * In Crowdin:
 * Source strings refers to Symfony's translation keys
 */
final class CrowdinProvider extends AbstractProvider
{
    protected const HOST = 'crowdin.com/api/v2';

    private $projectId;
    private $token;
    private $loader;
    private $logger;
    private $defaultLocale;

    public function __construct(string $projectId, string $token, HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->projectId = $projectId;
        $this->token = $token;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;

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
        foreach ($translations->getCatalogues() as $catalogue) {
            foreach ($catalogue->all() as $domain => $messages) {
                $locale = $catalogue->getLocale();

                // check if domain exists, if not, create it

                foreach ($messages as $id => $message) {
                    $this->addString($id);
                    $this->addTranslation($id, $message, $locale);
                }
            }
        }
    }

    /**
     * @see https://support.crowdin.com/api/v2/#operation/api.projects.translations.exports.post
     */
    public function read(array $domains, array $locales): TranslatorBag
    {
        $filter = $domains ? implode(',', $domains) : '*';
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            $fileId = $this->getFileId();

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

    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    /**
     * This function allows creation of a new translation key.
     *
     * @see https://support.crowdin.com/api/v2/#operation/api.projects.strings.post
     */
    private function addString(string $id): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/projects/%s/strings', $this->getEndpoint(), $this->projectId), [
            'headers' => $this->getDefaultHeaders(),
            'body' => [
                'text' => $id,
                'identifier' => $id,
            ],
        ]);

        if (Response::HTTP_CONFLICT === $response->getStatusCode()) {
            $this->logger->warning(sprintf('Translation key (%s) already exists in Crowdin.', $id), [
                'id' => $id,
            ]);
        } elseif (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new TransportException(sprintf('Unable to add new translation key (%s) to Crowdin: (status code: "%s") "%s".', $id, $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    /**
     * This function allows translation of a message.
     *
     * @see https://support.crowdin.com/api/v2/#operation/api.projects.translations.post
     */
    private function addTranslation(string $id, string $message, string $locale): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/projects/%s/translations', $this->getEndpoint(), $this->projectId), [
            'headers' => $this->getDefaultHeaders(),
            'body' => [
                'stringId' => $id,
                'languageId' => $locale,
                'text' => $message,
            ],
        ]);

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new TransportException(sprintf('Unable to add new translation message "%s" (for key: "%s") to Crowdin: (status code: "%s") "%s".', $message, $id, $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    /**
     * @todo: Not sure at all of this
     */
    private function getFileId(): int
    {
        $response = $this->client->request('GET', sprintf('https://%s/projects/%s/files', $this->getEndpoint(), $this->projectId), [
            'headers' => $this->getDefaultHeaders(),
        ]);
        $files = json_decode($response->getContent());

        return $files->data[0]->data->id;
    }
}
