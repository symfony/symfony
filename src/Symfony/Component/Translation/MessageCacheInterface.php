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
 * @author Abdellatif Ait Boudad <a.aitboudad@gmail.com>
 */
interface MessageCacheInterface
{
    /**
     * Returns true if the cache is still fresh.
     *
     * @param string $locale
     * @param array  $options
     *
     * @return bool
     */
    public function isFresh($locale, array $options = array());

    /**
     * Loads a catalogue.
     *
     * @param string $locale The locale
     *
     * @return MessageCatalogueInterface A MessageCatalogue instance
     */
    public function load($locale);

    /**
     * Dumps the message catalogue.
     *
     * @param MessageCatalogueInterface $messages The message catalogue
     * @param array                     $options  Options that are used by the dumper
     */
    public function dump(MessageCatalogueInterface $messages, array $options = array());
}
