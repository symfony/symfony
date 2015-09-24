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

/**
 * Implements the FallbackLocaleAwareInterface by using the
 * TranslatorBagInterface.
 *
 * It will iterate along the catalogue chain in order to find the
 * first locale that contains a given message.
 *
 * Needed for a BC transition from Symfony 2.8 to 3.0. To be removed
 * Symfony 3.0.
 *
 * @deprecated
 */
class TranslatorBagToFallbackLocaleAwareAdapter implements FallbackLocaleAwareInterface
{
    private $translatorBag;

    public function __construct(TranslatorBagInterface $translatorBag)
    {
        $this->translatorBag = $translatorBag;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveLocale($id, $domain = null, $locale = null)
    {
        $id = (string) $id;
        $catalogue = $this->translatorBag->getCatalogue($locale);
        $locale = $catalogue->getLocale();

        while (!$catalogue->defines($id, $domain)) {
            if ($cat = $catalogue->getFallbackCatalogue()) {
                $catalogue = $cat;
                $locale = $catalogue->getLocale();
            } else {
                return;
            }
        }

        return $locale;
    }
}
