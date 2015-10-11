<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\MessageCatalogueProvider;

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator;

/**
 * MessageCatalogueProvider loads catalogue from resources.
 *
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class ResourceMessageCatalogueProvider implements MessageCatalogueProviderInterface
{
    /**
     * @var array
     */
    private $resources = array();

    /**
     * @var LoaderInterface[] An array of LoaderInterface objects
     */
    private $loaders = array();

    /**
     * @var array
     */
    private $fallbackLocales;

    /**
     * @var MessageCatalogueInterface[]
     */
    private $catalogues;

    /**
     * @param LoaderInterface[] $loaders         An array of loaders
     * @param array             $resources       An array of resources
     * @param array             $fallbackLocales The fallback locales.
     */
    public function __construct(array $loaders = array(), $resources = array(), $fallbackLocales = array())
    {
        $this->setFallbackLocales($fallbackLocales);
        foreach ($loaders as $format => $loader) {
            $this->addLoader($format, $loader);
        }

        foreach ($resources as $resource) {
            $this->addResource($resource[0], $resource[1], $resource[2], isset($resource[3]) ? $resource[3] : null);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale)
    {
        if (isset($this->catalogues[$locale])) {
            return $this->catalogues[$locale];
        }

        $catalogue = $this->loadCatalogue($locale);
        $this->loadFallbackCatalogues($catalogue);

        return $this->catalogues[$locale] = $catalogue;
    }

    /**
     * Adds a Resource.
     *
     * @param string $format   The name of the loader (@see addLoader())
     * @param mixed  $resource The resource name
     * @param string $locale   The locale
     * @param string $domain   The domain
     */
    public function addResource($format, $resource, $locale, $domain = null)
    {
        Translator::assertLocale($locale);

        if (null === $domain) {
            $domain = 'messages';
        }

        $this->resources[$locale][] = array($format, $resource, $domain);
        if (in_array($locale, $this->fallbackLocales)) {
            $this->catalogues = array();
        } else {
            unset($this->catalogues[$locale]);
        }
    }

    /**
     * Adds a Loader.
     *
     * @param string          $format The name of the loader (@see addResource())
     * @param LoaderInterface $loader A LoaderInterface instance
     */
    public function addLoader($format, LoaderInterface $loader)
    {
        $this->loaders[$format] = $loader;
    }

    /**
     * Returns the registered loaders.
     *
     * @return LoaderInterface[] An array of LoaderInterface instances
     */
    public function getLoaders()
    {
        return $this->loaders;
    }

    /**
     * Gets the registered resources.
     *
     * @return array
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * Sets the fallback locales.
     *
     * @param array $locales The fallback locales
     */
    public function setFallbackLocales(array $locales)
    {
        // needed as the fallback locales are linked to the already loaded catalogues
        $this->catalogues = array();

        foreach ($locales as $locale) {
            Translator::assertLocale($locale);
        }

        $this->fallbackLocales = $locales;
    }

    /**
     * Gets the fallback locales.
     *
     * @return array $locales The fallback locales
     */
    public function getFallbackLocales()
    {
        return $this->fallbackLocales;
    }

    /**
     * This method is public because it is needed in the Translator for BC. It should be made private in 3.0.
     *
     * @internal
     */
    public function loadCatalogue($locale)
    {
        $catalogue = new MessageCatalogue($locale);

        $loaders = $this->getLoaders();
        $resources = $this->getResources();
        foreach ((isset($resources[$locale]) ? $resources[$locale] : array()) as $resource) {
            if (!isset($loaders[$resource[0]])) {
                throw new \RuntimeException(sprintf('The "%s" translation loader is not registered.', $resource[0]));
            }

            $catalogue->addCatalogue($this->loaders[$resource[0]]->load($resource[1], $locale, $resource[2]));
        }

        return $catalogue;
    }

    /**
     * This method is public because it is needed in the Translator for BC. It should be made private in 3.0.
     *
     * @internal
     */
    public function computeFallbackLocales($locale)
    {
        $locales = array();
        foreach ($this->fallbackLocales as $fallback) {
            if ($fallback === $locale) {
                continue;
            }

            $locales[] = $fallback;
        }

        if (strrchr($locale, '_') !== false) {
            array_unshift($locales, substr($locale, 0, -strlen(strrchr($locale, '_'))));
        }

        return array_unique($locales);
    }

    private function loadFallbackCatalogues($catalogue)
    {
        $current = $catalogue;
        foreach ($this->computeFallbackLocales($catalogue->getLocale()) as $fallback) {
            $catalogue = isset($this->catalogues[$fallback]) ? $this->catalogues[$fallback] : $this->loadCatalogue($fallback);

            $fallbackCatalogue = new MessageCatalogue($fallback, $catalogue->all());
            $current->addFallbackCatalogue($fallbackCatalogue);
            $current = $fallbackCatalogue;
        }
    }
}
