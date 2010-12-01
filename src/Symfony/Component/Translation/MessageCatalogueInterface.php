<?php

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Resource\ResourceInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * MessageCatalogueInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface MessageCatalogueInterface
{
    /**
     * Gets the catalogue locale.
     *
     * @return string The locale
     */
    function getLocale();

    /**
     * Gets the domains.
     *
     * @return array An array of domains
     */
    function getDomains();

    /**
     * Gets the messages within a given domain.
     *
     * If $domain is null, it returns all messages.
     *
     * @param string $domain The domain name
     *
     * @return array An array of messages
     */
    function all($domain = null);

    /**
     * Sets a message translation.
     *
     * @param string $id          The message id
     * @param string $translation The messages translation
     * @param string $domain      The domain name
     */
    function set($id, $translation, $domain = 'messages');

    /**
     * Checks if a message has a translation.
     *
     * @param string $id     The message id
     * @param string $domain The domain name
     *
     * @return Boolean true if the message has a translation, false otherwise
     */
    function has($id, $domain = 'messages');

    /**
     * Gets a message translation.
     *
     * @param string $id     The message id
     * @param string $domain The domain name
     *
     * @return string The message translation
     */
    function get($id, $domain = 'messages');

    /**
     * Sets translations for a given domain.
     *
     * @param string $messages An array of translations
     * @param string $domain   The domain name
     */
    function replace($messages, $domain = 'messages');

    /**
     * Adds translations for a given domain.
     *
     * @param string $messages An array of translations
     * @param string $domain   The domain name
     */
    function add($messages, $domain = 'messages');

    /**
     * Merges translations from the given Catalogue into the current one.
     *
     * The two catalogues must have the same locale.
     *
     * @param MessageCatalogueInterface $catalogue A MessageCatalogueInterface instance
     */
    function addCatalogue(MessageCatalogueInterface $catalogue);

    /**
     * Merges translations from the given Catalogue into the current one
     * only when the translation does not exist.
     *
     * This is used to provide default translations when they do not exist for the current locale.
     *
     * @param MessageCatalogueInterface $catalogue A MessageCatalogueInterface instance
     */
    function addFallbackCatalogue(MessageCatalogueInterface $catalogue);

    /**
     * Returns an array of resources loaded to build this collection.
     *
     * @return ResourceInterface[] An array of resources
     */
    function getResources();

    /**
     * Adds a resource for this collection.
     *
     * @param ResourceInterface $resource A resource instance
     */
    function addResource(ResourceInterface $resource);
}
