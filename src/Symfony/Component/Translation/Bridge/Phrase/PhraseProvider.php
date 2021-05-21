<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Phrase;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PhraseProvider implements ProviderInterface
{
    private $client;
    private $endpoint;

    public function __construct(HttpClientInterface $client, string $endpoint)
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
    }

    public function __toString(): string
    {
        return sprintf('phrase://%s', $this->endpoint);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        // TODO: Implement write() method.
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $localeIds = $this->getLocaleIds($locales);

        $bag = new TranslatorBag();
        foreach ($localeIds as $locale => $id) {
            $messages = $this->getMessages($id);
            $bag->addCatalogue(new MessageCatalogue($locale, $messages));
        }

        return $bag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        // TODO: Implement delete() method.
    }

    private function getLocaleIds(array $locales): array
    {
        $availableLocales = $this->client->request('GET', 'locales')->toArray();

        $localeIds = [];
        foreach ($availableLocales as $locale) {
            $code = str_replace('-', '_', $locale['code'] ?? '');
            if (array_key_exists('id', $locale) && in_array($code, $locales, true)) {
                $localeIds[$code] = $locale['id'];
            }
        }

        return $localeIds;
    }

    private function getMessages(string $localeId): array
    {
        $path = sprintf('locales/%s/translations', $localeId);
        $translations = $this->client->request('GET', $path)->toArray();

        $messages = [];
        foreach ($translations as $translation) {
            $messages[$translation['key']['name']] = $translation['content'];
        }

        return ['messages' => $messages];
    }
}
