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

use Symfony\Component\Config\Resource\ResourceInterface;

/**
 * MessageCatalogueInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface MessageCatalogueInterface
{
    /**
     * Gets the catalogue locale.
     *
     * @return string The locale
     */
    public function getLocale();

    /**
     * Gets the domains.
     *
     * @return array An array of domains
     */
    public function getDomains();

    /**
     * Gets the messages within a given domain.
     *
     * If $domain is null, it returns all messages.
     *
     * @param string|null $domain The domain name
     *
     * @return array An array of messages
     */
    public function all($domain = null);

    /**
     * Sets a message translation.
     *
     * @param string      $id          The message id
     * @param string      $translation The messages translation
     * @param string|null $domain      The domain name
     */
    public function set($id, $translation, $domain = null);

    /**
     * Checks if a message has a translation.
     *
     * @param string      $id     The message id
     * @param string|null $domain The domain name
     *
     * @return bool true if the message has a translation, false otherwise
     */
    public function has($id, $domain = null);

    /**
     * Checks if a message has a translation (it does not take into account the fallback mechanism).
     *
     * @param string      $id     The message id
     * @param string|null $domain The domain name
     *
     * @return bool true if the message has a translation, false otherwise
     */
    public function defines($id, $domain = null);

    /**
     * Gets a message translation.
     *
     * @param string      $id     The message id
     * @param string|null $domain The domain name
     *
     * @return string The message translation
     */
    public function get($id, $domain = null);

    /**
     * Sets translations for a given domain.
     *
     * @param array       $messages An array of translations
     * @param string|null $domain   The domain name
     */
    public function replace($messages, $domain = null);

    /**
     * Adds translations for a given domain.
     *
     * @param array       $messages An array of translations
     * @param string|null $domain   The domain name
     */
    public function add($messages, $domain = null);

    /**
     * Merges translations from the given Catalogue into the current one.
     *
     * The two catalogues must have the same locale.
     *
     * @param self $catalogue
     */
    public function addCatalogue(MessageCatalogueInterface $catalogue);

    /**
     * Merges translations from the given Catalogue into the current one
     * only when the translation does not exist.
     *
     * This is used to provide default translations when they do not exist for the current locale.
     *
     * @param self $catalogue
     */
    public function addFallbackCatalogue(MessageCatalogueInterface $catalogue);

    /**
     * Gets the fallback catalogue.
     *
     * @return self|null A MessageCatalogueInterface instance or null when no fallback has been set
     */
    public function getFallbackCatalogue();

    /**
     * Returns an array of resources loaded to build this collection.
     *
     * @return ResourceInterface[] An array of resources
     */
    public function getResources();

    /**
     * Adds a resource for this collection.
     *
     * @param ResourceInterface $resource A resource instance
     */
    public function addResource(ResourceInterface $resource);
}
