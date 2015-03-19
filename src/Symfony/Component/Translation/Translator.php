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

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Translation\Catalogue\CatalogueFactoryInterface;
use Symfony\Component\Translation\Catalogue\CatalogueFactory;

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Translator implements TranslatorInterface, TranslatorBagInterface
{
    /**
     * @var MessageCatalogueInterface[]
     */
    protected $catalogues = array();

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var array
     */
    private $fallbackLocales = array();

    /**
     * @var LoaderInterface[]
     */
    private $loaders = array();

    /**
     * @var array
     */
    private $resources = array();

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
     * @var CatalogueFactoryInterface
     */
    private $catalogueFactory;

    /**
     * @param string                    $locale           The locale
     * @param MessageSelector|null      $selector         The message selector for pluralization
     * @param string|null               $cacheDir         The directory to use for the cache
     * @param bool                      $debug            Use cache in debug mode ?
     * @param CatalogueFactoryInterface $catalogueFactory The catalogue factory
     *
     * @throws \InvalidArgumentException If a locale contains invalid characters
     *
     * @api
     */
    public function __construct($locale, MessageSelector $selector = null, $cacheDir = null, $debug = false, CatalogueFactoryInterface $catalogueFactory = null)
    {
        $this->setLocale($locale);
        $this->selector = $selector ?: new MessageSelector();
        $this->cacheDir = $cacheDir;
        $this->debug = $debug;
        $this->catalogueFactory = $catalogueFactory ?: new CatalogueFactory();
    }

    /**
     * Adds a Loader.
     *
     * @param string          $format The name of the loader (@see addResource())
     * @param LoaderInterface $loader A LoaderInterface instance
     *
     * @api
     */
    public function addLoader($format, LoaderInterface $loader)
    {
        $this->loaders[$format] = $loader;
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
     * @api
     */
    public function addResource($format, $resource, $locale, $domain = null)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        $this->assertValidLocale($locale);

        $this->resources[$locale][] = array($format, $resource, $domain);

        if (in_array($locale, $this->fallbackLocales)) {
            $this->catalogues = array();
        } else {
            unset($this->catalogues[$locale]);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setLocale($locale)
    {
        $this->assertValidLocale($locale);
        $this->locale = $locale;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Sets the fallback locale(s).
     *
     * @param string|array $locales The fallback locale(s)
     *
     * @throws \InvalidArgumentException If a locale contains invalid characters
     *
     * @deprecated since version 2.3, to be removed in 3.0. Use setFallbackLocales() instead.
     *
     * @api
     */
    public function setFallbackLocale($locales)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.3 and will be removed in 3.0. Use the setFallbackLocales() method instead.', E_USER_DEPRECATED);

        $this->setFallbackLocales(is_array($locales) ? $locales : array($locales));
    }

    /**
     * Sets the fallback locales.
     *
     * @param array $locales The fallback locales
     *
     * @throws \InvalidArgumentException If a locale contains invalid characters
     *
     * @api
     */
    public function setFallbackLocales(array $locales)
    {
        // needed as the fallback locales are linked to the already loaded catalogues
        $this->catalogues = array();

        foreach ($locales as $locale) {
            $this->assertValidLocale($locale);
        }

        $this->fallbackLocales = $locales;
    }

    /**
     * Gets the fallback locales.
     *
     * @return array $locales The fallback locales
     *
     * @api
     */
    public function getFallbackLocales()
    {
        return $this->fallbackLocales;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        } else {
            $this->assertValidLocale($locale);
        }

        if (null === $domain) {
            $domain = 'messages';
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        return strtr($this->catalogues[$locale]->get((string) $id, $domain), $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        } else {
            $this->assertValidLocale($locale);
        }

        if (null === $domain) {
            $domain = 'messages';
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        $id = (string) $id;

        $catalogue = $this->catalogues[$locale];
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
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        return $this->catalogues[$locale];
    }

    /**
     * Gets the loaders.
     *
     * @return array LoaderInterface[]
     */
    protected function getLoaders()
    {
        return $this->loaders;
    }

    /**
     * Collects all messages for the given locale.
     *
     * @param string|null $locale Locale of translations, by default is current locale
     *
     * @return array[array] indexed by catalog
     */
    public function getMessages($locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        $catalogues = array();
        $catalogues[] = $catalogue = $this->catalogues[$locale];
        while ($catalogue = $catalogue->getFallbackCatalogue()) {
            $catalogues[] = $catalogue;
        }
        $messages = array();
        for ($i = count($catalogues) - 1; $i >= 0; $i--) {
            $localeMessages = $catalogues[$i]->all();
            $messages = array_replace_recursive($messages, $localeMessages);
        }

        return $messages;
    }

    /*
     * @param string $locale
     */
    protected function loadCatalogue($locale)
    {
        if (null === $this->cacheDir) {
            $this->initializeCatalogue($locale);
        } else {
            $this->initializeCacheCatalogue($locale);
        }
    }

    /**
     * @param string $locale
     */
    protected function initializeCatalogue($locale)
    {
        $this->assertValidLocale($locale);

        try {
            $this->doLoadCatalogue($locale);
        } catch (NotFoundResourceException $e) {
            if (!$this->computeFallbackLocales($locale)) {
                throw $e;
            }
        }
        $this->loadFallbackCatalogues($locale);
    }

    /**
     * @param string $locale
     * @param bool   $forceRefresh
     */
    private function initializeCacheCatalogue($locale, $forceRefresh = false)
    {
        if (isset($this->catalogues[$locale])) {
            return;
        }

        $this->assertValidLocale($locale);
        $cache = new ConfigCache($this->cacheDir.'/catalogue.'.$locale.'.php', $this->debug);
        if ($forceRefresh || !$cache->isFresh()) {
            $this->initializeCatalogue($locale);

            $fallbackContent = '';
            $current = '';
            $replacementPattern = '/[^a-z0-9_]/i';
            foreach ($this->computeFallbackLocales($locale) as $fallback) {
                $fallbackSuffix = ucfirst(preg_replace($replacementPattern, '_', $fallback));
                $currentSuffix = ucfirst(preg_replace($replacementPattern, '_', $current));

                $fallbackContent .= sprintf(<<<EOF
\$catalogue%s = new MessageCatalogue('%s', %s);
\$catalogue%s->addFallbackCatalogue(\$catalogue%s);


EOF
                    ,
                    $fallbackSuffix,
                    $fallback,
                    var_export($this->catalogues[$fallback]->all(), true),
                    $currentSuffix,
                    $fallbackSuffix
                );
                $current = $fallback;
            }

            $content = sprintf(<<<EOF
<?php

use Symfony\Component\Translation\MessageCatalogue;

\$resourcesHash = '%s';
\$catalogue = new MessageCatalogue('%s', %s);

%s
return array(\$catalogue, \$resourcesHash);

EOF
                ,
                $this->getResourcesHash($locale),
                $locale,
                var_export($this->catalogues[$locale]->all(), true),
                $fallbackContent
            );

            $cache->write($content, $this->catalogues[$locale]->getResources());

            return;
        }

        $catalogue = include $cache;

        /*
         * Old cache returns only the catalogue, without resourcesHash
         */
        $resourcesHash = null;
        if (is_array($catalogue)) {
            list($catalogue, $resourcesHash) = $catalogue;
        }

        if ($this->debug && $resourcesHash !== $this->getResourcesHash($locale)) {
            return $this->initializeCacheCatalogue($locale, true);
        }

        $this->catalogues[$locale] = $catalogue;
    }

    private function getResourcesHash($locale)
    {
        if (!isset($this->resources[$locale])) {
            return '';
        }

        return sha1(serialize($this->resources[$locale]));
    }

    private function doLoadCatalogue($locale)
    {
        $this->catalogues[$locale] = $this->catalogueFactory->create($locale);

        if (isset($this->resources[$locale])) {
            foreach ($this->resources[$locale] as $resource) {
                if (!isset($this->loaders[$resource[0]])) {
                    throw new \RuntimeException(sprintf('The "%s" translation loader is not registered.', $resource[0]));
                }
                $this->catalogues[$locale]->addCatalogue($this->loaders[$resource[0]]->load($resource[1], $locale, $resource[2]));
            }
        }
    }

    private function loadFallbackCatalogues($locale)
    {
        $current = $this->catalogues[$locale];

        foreach ($this->computeFallbackLocales($locale) as $fallback) {
            if (!isset($this->catalogues[$fallback])) {
                $this->doLoadCatalogue($fallback);
            }

            $current->addFallbackCatalogue($this->catalogues[$fallback]);
            $current = $this->catalogues[$fallback];
        }
    }

    protected function computeFallbackLocales($locale)
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

    /**
     * Asserts that the locale is valid, throws an Exception if not.
     *
     * @param string $locale Locale to tests
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     */
    protected function assertValidLocale($locale)
    {
        if (1 !== preg_match('/^[a-z0-9@_\\.\\-]*$/i', $locale)) {
            throw new \InvalidArgumentException(sprintf('Invalid "%s" locale.', $locale));
        }
    }
}
