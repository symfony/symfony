<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Remote;

use Symfony\Component\Translation\TranslatorBag;

/**
 * Remote is used to sync translations with a remote.
 */
interface RemoteInterface
{
    /**
     * Write given translation to the remote.
     *
     * * Translations available in the MessageCatalogue only must be created.
     * * Translations available in both the MessageCatalogue and on the remote
     * must be overwritten.
     * * Translations available on the remote only must be kept.
     */
    public function write(TranslatorBag $translations, bool $override = false): void;

    /**
     * This method must return asked translations.
     */
    public function read(array $domains, array $locales): TranslatorBag;

    /**
     * This method must delete all translation given in the TranslatorBag.
     */
    public function delete(TranslatorBag $translations): void;
}
