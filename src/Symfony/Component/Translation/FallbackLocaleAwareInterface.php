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
 * An implementing class handles multiple locales and possible MessageCatalogues
 * to find appropriate translations with some kind of fallback strategy.
 *
 * Through this interface it is possible to query which locale the translation
 * for a given message id and domain will be taken from.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
interface FallbackLocaleAwareInterface
{
    /**
     * Determines the locale that the translation will ultimately be taken from.
     *
     * @param string      $id     The message id (may also be an object that can be cast to string)
     * @param string|null $domain The domain for the message or null to use the default
     * @param string|null $locale The locale or null to use the default
     *
     * @return string|null The locale of the best available translation or null for unknown messages.
     */
    public function resolveLocale($id, $domain = null, $locale = null);
}
