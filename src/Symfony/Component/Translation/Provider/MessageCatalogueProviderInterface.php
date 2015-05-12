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

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * MessageCatalogueProviderInterface describes a class that can take
 * Loaders (@see LoaderInterface), some resources and then provide
 * a MessageCatalogue chain for a given primary and possibly additional
 * fallback locales.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
interface MessageCatalogueProviderInterface
{
    /**
     * Adds a Loader.
     *
     * @param string          $format The name of the loader (@see addResource())
     * @param LoaderInterface $loader A LoaderInterface instance
     */
    public function addLoader($format, LoaderInterface $loader);

    /**
     * Gets the loaders.
     *
     * @return array LoaderInterface[]
     */
    public function getLoaders();

    /**
     * Adds a Resource.
     *
     * @param string $format   The name of the loader (@see addLoader())
     * @param mixed  $resource The resource name
     * @param string $locale   The locale
     * @param string $domain   The domain
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     */
    public function addResource($format, $resource, $locale, $domain = null);

    /**
     * Provide a MessageCatalogue chain.
     *
     * @param $locale          The primary locale
     * @param $fallbackLocales Locales (in order) for the fallback catalogues
     *
     * @return MessageCatalogueInterface
     */
    public function provideCatalogue($locale, $fallbackLocales = array());
}
