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
use Symfony\Component\Translation\TranslatorBagInterface;

interface ProviderInterface extends \Stringable
{
    /**
     * Translations available in the TranslatorBag only must be created.
     * Translations available in both the TranslatorBag and on the provider
     * must be overwritten.
     * Translations available on the provider only must be kept.
     * Locales available in the TranslatorBag only must be created on the provider.
     */
    public function write(TranslatorBagInterface $translatorBag): void;

    /**
     * @param string[] $domains The domains to read, or none to read all the domains available on the provider
     * @param string[] $locales The locales to read, or none to read all the locales available on the provider
     */
    public function read(array $domains, array $locales): TranslatorBag;

    public function delete(TranslatorBagInterface $translatorBag): void;
}
