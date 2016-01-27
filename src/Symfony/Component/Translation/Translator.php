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

use Symfony\Component\Translation\MessageCatalogueProvider\MessageCatalogueProviderInterface;
use Symfony\Component\Translation\MessageCatalogueProvider\MessageCatalogueProvider;
use Symfony\Component\Translation\MessageCatalogueProvider\CachedMessageCatalogueProvider;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheFactory;

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Translator implements TranslatorInterface, TranslatorBagInterface
{
    /**
     * @var MessageCatalogueInterface[]
     *
     * Deprecated since version 3.0, to be removed in 4.0. Use Translator::getCatalogue instead.
     */
    protected $catalogues = array();

    /**
     * @var string
     */
    private $locale;

    /**
     * @var MessageSelector
     */
    private $selector;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var MessageCatalogueProviderInterface
     */
    private $messageCatalogueProvider;

    /**
     * @var MessageCatalogueProvider
     */
    private $resourceMessageCatalogueProvider;

    /**
     * @var CachedMessageCatalogueProvider
     */
    private $cacheMessageCatalogueProvider;

    /**
     * @param string                                                 $locale                   The locale
     * @param MessageCatalogueProviderInterface|MessageSelector|null $messageCatalogueProvider The MessageCatalogueProviderInterface or MessageSelector
     *                                                                                         Passing the MessageSelector or null as a second parameter is deprecated since version 2.8.
     * @param MessageSelector|string|null                            $selector                 The MessageSelector or cache directory
     *                                                                                         Passing the cache directory as a third parameter is deprecated since version 2.8.
     * @param bool                                                   $debug                    Use cache in debug mode ?
     *                                                                                         Deprecated since version 3.0, to be removed in 4.0.
     *
     * @throws \InvalidArgumentException If a locale contains invalid characters
     */
    public function __construct($locale, $messageCatalogueProvider = null, $selector = null, $debug = false)
    {
        $this->setLocale($locale);

        if ($messageCatalogueProvider instanceof MessageCatalogueProviderInterface) {
            $this->messageCatalogueProvider = $messageCatalogueProvider;
            $this->selector = $selector ?: new MessageSelector();
        } else {
            @trigger_error('The '.__CLASS__.' constructor will require a MessageCatalogueProviderInterface for its second argument since 3.0.', E_USER_DEPRECATED);

            // Parameters are shifted of one offset
            $this->selector = $messageCatalogueProvider ?: new MessageSelector();
            $this->cacheDir = $selector;
            $this->debug = $debug;
        }

        if (!$this->selector instanceof MessageSelector) {
            throw new \InvalidArgumentException(sprintf('The message selector "%s" must be an instance of MessageSelector.', get_class($this->selector)));
        }

        if ($this->isMethodOverwritten('assertValidLocale')) {
            @trigger_error('The Translator::assertValidLocale method is deprecated since version 2.8 and will be removed in 3.0. Use Translator::assertLocale method instead.', E_USER_DEPRECATED);
        }
    }

    /**
     * Sets the ConfigCache factory to use.
     *
     * @param ConfigCacheFactoryInterface $configCacheFactory
     *
     * @Deprecated since version 3.0, to be removed in 4.0. Rely on CachedMessageCatalogueProvider instead.
     */
    public function setConfigCacheFactory(ConfigCacheFactoryInterface $configCacheFactory)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Rely on CachedMessageCatalogueProvider instead.', E_USER_DEPRECATED);

        $this->getCachedMessageCatalogueProvider()->setConfigCacheFactory($configCacheFactory);
    }

    /**
     * Adds a Loader.
     *
     * @param string          $format The name of the loader (@see addResource())
     * @param LoaderInterface $loader A LoaderInterface instance
     *
     * @Deprecated since version 3.0, to be removed in 4.0. Use MessageCatalogueProvider::addLoader instead.
     */
    public function addLoader($format, LoaderInterface $loader)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use MessageCatalogueProvider::addLoader instead.', E_USER_DEPRECATED);

        $this->getResourceMessageCatalogueProvider()->addLoader($format, $loader);
    }

    /**
     * Adds a Resource.
     *
     * @param string $format   The name of the loader (@see addLoader())
     * @param mixed  $resource The resource name
     * @param string $locale   The locale
     * @param string $domain   The domain
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     *
     * @Deprecated since version 3.0, to be removed in 4.0. Use MessageCatalogueProvider::addResource instead.
     */
    public function addResource($format, $resource, $locale, $domain = null)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use MessageCatalogueProvider::addResource instead.', E_USER_DEPRECATED);

        $this->getResourceMessageCatalogueProvider()->addResource($format, $resource, $locale, $domain);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->assertValidLocale($locale);
        $this->locale = $locale;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Sets the fallback locales.
     *
     * @param array $locales The fallback locales
     *
     * @throws \InvalidArgumentException If a locale contains invalid characters
     *
     * @Deprecated since version 3.0, to be removed in 4.0. Use MessageCatalogueProvider::setFallbackLocales instead.
     */
    public function setFallbackLocales(array $locales)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use MessageCatalogueProvider::setFallbackLocales instead.', E_USER_DEPRECATED);

        $this->getResourceMessageCatalogueProvider()->setFallbackLocales($locales);
    }

    /**
     * Gets the fallback locales.
     *
     * @return array $locales The fallback locales
     *
     * @Deprecated since version 3.0, to be removed in 4.0. Use MessageCatalogueProvider::getFallbackLocales instead.
     */
    public function getFallbackLocales()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use MessageCatalogueProvider::getFallbackLocales instead.', E_USER_DEPRECATED);

        return $this->getResourceMessageCatalogueProvider()->getFallbackLocales();
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        return strtr($this->getCatalogue($locale)->get((string) $id, $domain), $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        $id = (string) $id;
        $catalogue = $this->getCatalogue($locale);
        $locale = $catalogue->getLocale();
        while (!$catalogue->defines($id, $domain)) {
            if ($cat = $catalogue->getFallbackCatalogue()) {
                $catalogue = $cat;
                $locale = $catalogue->getLocale();
            } else {
                break;
            }
        }

        return strtr($this->selector->choose($catalogue->get($id, $domain), (int) $number, $locale), $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        } else {
            $this->assertValidLocale($locale);
        }

        // check if the Translator class is overwritten
        if ('Symfony\Component\Translation\Translator' !== get_class($this) && !$this->messageCatalogueProvider) {
            if (isset($this->catalogues[$locale])) {
                return $this->catalogues[$locale];
            }

            if ($this->isMethodOverwritten('loadCatalogue')) {
                @trigger_error('The Translator::loadCatalogue method is deprecated since version 2.8 and will be removed in 3.0. Rely on MessageCatalogueProviderInterface::getCatalogue() instead.', E_USER_DEPRECATED);
            }

            if ($this->isMethodOverwritten('getLoaders')) {
                @trigger_error('The Translator::getLoaders method is deprecated since version 2.8 and will be removed in 3.0. Rely on MessageCatalogueProvider::getLoaders instead.', E_USER_DEPRECATED);
            }

            $this->loadCatalogue($locale);

            return $this->catalogues[$locale];
        }

        return $this->catalogues[$locale] = $this->getMessageCatalogueProvider()->getCatalogue($locale);
    }

    /**
     * Gets the loaders.
     *
     * @return array LoaderInterface[]
     *
     * @Deprecated since version 3.0, to be removed in 4.0. Rely on MessageCatalogueProvider::getLoaders instead.
     */
    protected function getLoaders()
    {
        return $this->getResourceMessageCatalogueProvider()->getLoaders();
    }

    /**
     * @param string $locale
     *
     * @Deprecated since version 3.0, to be removed in 4.0. Rely on MessageCatalogueProviderInterface::getCatalogue instead.
     */
    protected function loadCatalogue($locale)
    {
        if ($this->isMethodOverwritten('initializeCatalogue')) {
            @trigger_error('The Translator::initializeCatalogue method is deprecated since version 2.8 and will be removed in 3.0. Rely on MessageCatalogueProviderInterface::getCatalogue() instead.', E_USER_DEPRECATED);
        }

        if (null === $this->cacheDir) {
            $this->initializeCatalogue($locale);
        } else {
            $this->initializeCacheCatalogue($locale);
        }
    }

    /**
     * @param string $locale
     *
     * @Deprecated since version 3.0, to be removed in 4.0. Rely on MessageCatalogueProviderInterface::getCatalogue instead.
     */
    protected function initializeCatalogue($locale)
    {
        $this->assertValidLocale($locale);

        if ($this->isMethodOverwritten('computeFallbackLocales')) {
            @trigger_error('The Translator::computeFallbackLocales method is deprecated since version 2.8 and will be removed in 3.0. Rely on MessageCatalogueProvider instead.', E_USER_DEPRECATED);
        }

        try {
            $this->catalogues[$locale] = $this->getResourceMessageCatalogueProvider()->loadCatalogue($locale);
        } catch (NotFoundResourceException $e) {
            if (!$this->computeFallbackLocales($locale)) {
                throw $e;
            }
        }
        // load Fallback Catalogues
        $current = $this->catalogues[$locale];
        foreach ($this->computeFallbackLocales($locale) as $fallback) {
            if (!isset($this->catalogues[$fallback])) {
                $this->catalogues[$fallback] = $this->getResourceMessageCatalogueProvider()->loadCatalogue($fallback);
            }

            $fallbackCatalogue = new MessageCatalogue($fallback, $this->catalogues[$fallback]->all());
            $current->addFallbackCatalogue($fallbackCatalogue);
            $current = $fallbackCatalogue;
        }
    }

    /**
     * @param string $locale
     */
    private function initializeCacheCatalogue($locale)
    {
        if (isset($this->catalogues[$locale])) {
            /* Catalogue already initialized. */
            return;
        }

        $this->assertValidLocale($locale);
        $cache = $this->getCachedMessageCatalogueProvider()->cache($locale, function () use ($locale) {
            $this->initializeCatalogue($locale);

            return $this->catalogues[$locale];
        });

        if (isset($this->catalogues[$locale])) {
            /* Catalogue has been initialized as it was written out to cache. */
            return;
        }

        /* Read catalogue from cache. */
        $this->catalogues[$locale] = $cache;
    }

    /**
     * @Deprecated since version 3.0, to be removed in 4.0. Rely on MessageCatalogueProvider instead.
     */
    protected function computeFallbackLocales($locale)
    {
        return $this->getResourceMessageCatalogueProvider()->computeFallbackLocales($locale);
    }

    /**
     * Asserts that the locale is valid, throws an Exception if not.
     *
     * @param string $locale Locale to tests
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     */
    protected function assertValidLocale($locale)
    {
        self::assertLocale($locale);
    }

    /**
     * Asserts that the locale is valid, throws an Exception if not.
     *
     * @param string $locale Locale to tests
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     */
    public static function assertLocale($locale)
    {
        if (1 !== preg_match('/^[a-z0-9@_\\.\\-]*$/i', $locale)) {
            throw new \InvalidArgumentException(sprintf('Invalid "%s" locale.', $locale));
        }
    }

    private function getMessageCatalogueProvider()
    {
        if ($this->messageCatalogueProvider) {
            return $this->messageCatalogueProvider;
        }

        if (null !== $this->cacheDir) {
            return $this->getCachedMessageCatalogueProvider();
        }

        return $this->getResourceMessageCatalogueProvider();
    }

    private function getResourceMessageCatalogueProvider()
    {
        if ($this->messageCatalogueProvider instanceof MessageCatalogueProvider) {
            return $this->messageCatalogueProvider;
        }

        if ($this->resourceMessageCatalogueProvider) {
            return $this->resourceMessageCatalogueProvider;
        }

        return $this->resourceMessageCatalogueProvider = new MessageCatalogueProvider();
    }

    private function getCachedMessageCatalogueProvider()
    {
        if ($this->messageCatalogueProvider instanceof CachedMessageCatalogueProvider) {
            return $this->messageCatalogueProvider;
        }

        if ($this->cacheMessageCatalogueProvider) {
            return $this->cacheMessageCatalogueProvider;
        }

        return $this->cacheMessageCatalogueProvider = new CachedMessageCatalogueProvider($this->getResourceMessageCatalogueProvider(), new ConfigCacheFactory($this->debug), $this->cacheDir);
    }

    private function isMethodOverwritten($name)
    {
        $reflector = new \ReflectionMethod($this, $name);

        return $reflector->getDeclaringClass()->getName() !== 'Symfony\Component\Translation\Translator';
    }
}
