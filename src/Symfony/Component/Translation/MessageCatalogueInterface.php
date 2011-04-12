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
 *
 * @api
 */
interface MessageCatalogueInterface
{
    /**
     * Gets the catalogue locale.
     *
     * @return string The locale
     *
     * @api
     */
    function getLocale();

    /**
     * Gets the domains.
     *
     * @return array An array of domains
     *
     * @api
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
     *
     * @api
     */
    function all($domain = null);

    /**
     * Sets a message translation.
     *
     * @param string $id          The message id
     * @param string $translation The messages translation
     * @param string $domain      The domain name
     *
     * @api
     */
    function set($id, $translation, $domain = 'messages');

    /**
     * Checks if a message has a translation.
     *
     * @param string $id     The message id
     * @param string $domain The domain name
     *
     * @return Boolean true if the message has a translation, false otherwise
     *
     * @api
     */
    function has($id, $domain = 'messages');

    /**
     * Gets a message translation.
     *
     * @param string $id     The message id
     * @param string $domain The domain name
     *
     * @return string The message translation
     *
     * @api
     */
    function get($id, $domain = 'messages');

    /**
     * Sets translations for a given domain.
     *
     * @param string $messages An array of translations
     * @param string $domain   The domain name
     *
     * @api
     */
    function replace($messages, $domain = 'messages');

    /**
     * Adds translations for a given domain.
     *
     * @param string $messages An array of translations
     * @param string $domain   The domain name
     *
     * @api
     */
    function add($messages, $domain = 'messages');

    /**
     * Merges translations from the given Catalogue into the current one.
     *
     * The two catalogues must have the same locale.
     *
     * @param MessageCatalogueInterface $catalogue A MessageCatalogueInterface instance
     *
     * @api
     */
    function addCatalogue(MessageCatalogueInterface $catalogue);

    /**
     * Merges translations from the given Catalogue into the current one
     * only when the translation does not exist.
     *
     * This is used to provide default translations when they do not exist for the current locale.
     *
     * @param MessageCatalogueInterface $catalogue A MessageCatalogueInterface instance
     *
     * @api
     */
    function addFallbackCatalogue(MessageCatalogueInterface $catalogue);

    /**
     * Returns an array of resources loaded to build this collection.
     *
     * @return ResourceInterface[] An array of resources
     *
     * @api
     */
    function getResources();

    /**
     * Adds a resource for this collection.
     *
     * @param ResourceInterface $resource A resource instance
     *
     * @api
     */
    function addResource(ResourceInterface $resource);
}
