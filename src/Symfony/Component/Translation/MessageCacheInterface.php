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
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
interface MessageCacheInterface
{
    /**
     * Loads a catalogue from cache and (re-)initializes it if necessary.
     *
     * @param string $locale
     * @param array  $options
     *
     * @return MessageCatalogueInterface A MessageCatalogue instance
     */
    public function cache($locale, array $options = array());
}
