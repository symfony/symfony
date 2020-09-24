<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Provider;

use Symfony\Component\Translation\TranslatorBag;

/**
 * Providers are used to sync translations with a translation provider.
 */
interface ProviderInterface
{
    /**
     * Writes given translation to the provider.
     *
     * * Translations available in the MessageCatalogue only must be created.
     * * Translations available in both the MessageCatalogue and on the provider
     * must be overwritten.
     * * Translations available on the provider only must be kept.
     */
    public function write(TranslatorBag $translations, bool $override = false): void;

    /**
     * Returns asked translations.
     */
    public function read(array $domains, array $locales): TranslatorBag;

    /**
     * Delete all translation given in the TranslatorBag.
     */
    public function delete(TranslatorBag $translations): void;
}
