<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

final class TranslatorBag implements TranslatorBagInterface
{
    /** @var MessageCatalogue[] */
    private $catalogues = [];

    public function addCatalogue(MessageCatalogue $catalogue): void
    {
        $this->catalogues[$catalogue->getLocale()] = $catalogue;
    }

    public function getDomains(): array
    {
        $domains = [];

        foreach ($this->catalogues as $catalogue) {
            $domains += $catalogue->getDomains();
        }

        return array_unique($domains);
    }

    public function all(): array
    {
        $messages = [];

        foreach ($this->catalogues as $catalogue) {
            $locale = $catalogue->getLocale();
            if (!isset($messages[$locale])) {
                $messages[$locale] = $catalogue->all();
            } else {
                $messages[$locale] = array_merge($messages[$locale], $catalogue->all());
            }
        }

        return $messages;
    }

    public function getCatalogue(string $locale = null): ?MessageCatalogue
    {
        if (!$locale) {
            return null;
        }

        return $this->catalogues[$locale];
    }
}
